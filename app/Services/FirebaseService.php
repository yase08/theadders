<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\WebPushConfig;

class FirebaseService
{
  public $messaging;
  public $firebase;
  public $database;

  public function __construct()
  {
    $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'))->withDatabaseUri(config('services.firebase.database_url'));
    $this->messaging = $factory->createMessaging();
    $this->database = $factory->createDatabase();
  }

  /**
   * Kirim pesan dan notifikasi jika diperlukan
   * 
   * @param array $data Data pesan
   * @param bool $shouldSendNotification Flag untuk mengontrol pengiriman notifikasi
   * @return bool Status keberhasilan
   */
  public function sendMessage($data, $shouldSendNotification = true)
  {
    try {
      $messageRef = $this->storeMessage($data);

      if ($shouldSendNotification && !empty($data['receiver']->fcm_token)) {
        $shouldSkipNotification = false;

        if (isset($data['client_status'])) {
          if (in_array($data['client_status'], ['read', 'delivered', 'app_open'])) {
            $shouldSkipNotification = true;
          }
        }

        if (!$shouldSkipNotification) {
          $message = CloudMessage::withTarget('token', $data['receiver']->fcm_token)
            ->withNotification(Notification::create(
              'New message from ' . $data['sender']->fullname,
              substr($data['message'], 0, 100) . (strlen($data['message']) > 100 ? '...' : '')
            ))
            ->withData([
              'exchange_id' => $data['exchange_id'] ?? '',
              'sender_id' => (string)$data['sender']->users_id,
              'message_id' => $messageRef->getKey() ?? '',
              'type' => 'chat_message',
              'room_id' => $data['room_id'] ?? '',
              'priority' => $data['priority'] ?? 'normal',
              'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ])
            ->withAndroidConfig(AndroidConfig::fromArray([
              'priority' => 'high',
            ]))
            ->withApnsConfig(ApnsConfig::fromArray([
              'headers' => ['apns-priority' => '10'],
              'payload' => [
                'aps' => [
                  'content-available' => 1,
                  'mutable-content' => 1
                ]
              ]
            ]));
// 
          $this->messaging->send($message);
          Log::info('Notification sent for message: ' . ($messageRef->getKey() ?? 'unknown'));
        } else {
          Log::info('Notification skipped for message due to status: ' . ($data['client_status'] ?? 'unknown'));
        }
      }

      return true;
    } catch (\Exception $e) {
      \Log::error('Firebase error: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Simpan pesan ke Firebase Realtime Database
   * 
   * @param array $data Data pesan
   * @return \Kreait\Firebase\Database\Reference
   */
  private function storeMessage($data)
  {
    // Buat chat key yang menyertakan exchange_id untuk menghindari pencampuran pesan
    $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id, $data['exchange_id'] ?? null);
    $chatRef = $this->database->getReference('chats/' . $chatKey);

    $messageData = [
      'sender_id' => $data['sender']->users_id,
      'receiver_id' => $data['receiver']->users_id,
      'message' => $data['message'],
      'exchange_id' => $data['exchange_id'] ?? null,
      'timestamp' => time() * 1000,
      'status' => 'sent'
    ];

    // Push returns the new reference
    return $chatRef->push($messageData);
  }

  /**
   * Perbarui status pesan di Firebase
   * 
   * @param string $messageId ID pesan
   * @param string|int $senderId ID pengirim
   * @param string|int $receiverId ID penerima
   * @param string $status Status baru ('delivered', 'read')
   * @param string|int|null $exchangeId ID exchange (opsional)
   * @return bool Status keberhasilan
   */
  public function updateFirebaseMessageStatus($messageId, $senderId, $receiverId, $status, $exchangeId = null)
  {
    try {
      // Gunakan exchange_id jika tersedia untuk chat key yang lebih spesifik
      $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);
      $chatRef = $this->database->getReference('chats/' . $chatKey);

      // Coba cari pesan berdasarkan key yang diketahui terlebih dahulu
      $specificMessageRef = $this->database->getReference('chats/' . $chatKey . '/' . $messageId);
      $specificMessage = $specificMessageRef->getValue();

      if ($specificMessage) {
        // Pesan ditemukan dengan ID yang tepat, update langsung
        $specificMessageRef->update(['status' => $status]);
        Log::info("Message $messageId status updated to $status directly");
        return true;
      }

      // Jika tidak ditemukan, cari melalui semua pesan (fallback)
      $messages = $chatRef->getValue();
      if ($messages) {
        foreach ($messages as $key => $msg) {
          // Cari berdasarkan ID atau kombinasi sender/receiver/message
          if (($msg['message_id'] ?? null) == $messageId ||
            ($msg['sender_id'] == $senderId &&
              $msg['receiver_id'] == $receiverId &&
              (isset($msg['exchange_id']) ? $msg['exchange_id'] == $exchangeId : true))
          ) {
            $this->database->getReference('chats/' . $chatKey . '/' . $key)
              ->update(['status' => $status]);

            Log::info("Message found and status updated to $status via search");
            return true;
          }
        }
      }

      Log::warning("Message $messageId not found in chat $chatKey");
      return false;
    } catch (\Exception $e) {
      Log::error('Firebase update status error: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Buat chat key yang konsisten
   * 
   * @param string|int $userId1 ID user pertama
   * @param string|int $userId2 ID user kedua
   * @param string|int|null $exchangeId ID exchange (opsional)
   * @return string Chat key
   */
  private function getChatKey($userId1, $userId2, $exchangeId = null)
  {
    // Create consistent chat key
    $ids = [(string)$userId1, (string)$userId2];
    sort($ids);
    $baseKey = implode('_', $ids);

    // Tambahkan exchange_id jika tersedia
    if ($exchangeId) {
      return $baseKey . '_exchange_' . $exchangeId;
    }

    return $baseKey;
  }

  /**
   * Kirim notifikasi ke pengguna
   * 
   * @param string $token FCM token
   * @param string $title Judul notifikasi
   * @param string $body Isi notifikasi
   * @param array $data Data tambahan
   * @return array Status keberhasilan dan pesan
   */
  public function sendNotification($token, $title, $body, $data = [])
  {
    try {
      $notification = Notification::create($title, $body);

      $message = CloudMessage::withTarget('token', $token)
        ->withNotification($notification)
        ->withData($data)
        ->withAndroidConfig(AndroidConfig::fromArray([
          'priority' => 'high',
        ]))
        ->withApnsConfig(ApnsConfig::fromArray([
          'headers' => ['apns-priority' => '10'],
        ]))
        ->withWebPushConfig(WebPushConfig::fromArray([
          'headers' => ['TTL' => '4500'],
        ]));

      $this->messaging->send($message);

      return ['success' => true, 'message' => 'Notification send successfully'];
    } catch (MessagingException $e) {
      Log::error('MessagingException: ' . $e->getMessage());
      return ['success' => false, 'message' => $e->getMessage()];
    }
  }

  /**
   * Cek apakah pengguna sedang aktif di chat dengan pengguna lain
   * 
   * @param string|int $userId ID user
   * @param string|int $otherUserId ID user lainnya
   * @param string|int|null $exchangeId ID exchange (opsional)
   * @return bool Status keaktifan
   */
  public function isUserActiveInChat($userId, $otherUserId, $exchangeId = null)
  {
    try {
      // Dapatkan status user dari node 'user_status' di Firebase
      $userStatusRef = $this->database->getReference('user_status/' . $userId);
      $userStatus = $userStatusRef->getValue();

      if (!$userStatus) {
        return false; // User tidak aktif sama sekali
      }

      // Cek apakah user sedang aktif di chat dengan pengguna tertentu
      if (!empty($userStatus['active_chat'])) {
        $activeChat = $userStatus['active_chat'];

        // Jika exchange_id disediakan, cek juga exchange_id
        if ($exchangeId) {
          return $activeChat['user_id'] == $otherUserId && $activeChat['exchange_id'] == $exchangeId;
        }

        // Jika tidak, cek hanya user_id
        return $activeChat['user_id'] == $otherUserId;
      }

      return false;
    } catch (\Exception $e) {
      Log::error('Error checking user active status: ' . $e->getMessage());
      return false;
    }
  }

  public function updateClientStatus($userId, array $data)
  {
    try {
      $statusData = [
        'status' => $data['status'],
        'timestamp' => time() * 1000
      ];

      if (isset($data['active_chat'])) {
        $statusData['active_chat'] = $data['active_chat'];
      }

      $this->database->getReference('user_status/' . $userId)->update($statusData);
      return true;
    } catch (\Exception $e) {
      Log::error('Failed to update client status: ' . $e->getMessage());
      return false;
    }
  }
}
