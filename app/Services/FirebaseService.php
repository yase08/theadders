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
      $messageTimestamp = time() * 1000;

      
      $this->updateChatSummary(
        $data['sender']->users_id,
        $data['receiver']->users_id,
        $data['message'],
        $messageTimestamp,
        $data['exchange_id'] ?? null
      );

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
    
    $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id, $data['exchange_id'] ?? null);
    $chatRef = $this->database->getReference('chats/' . $chatKey);

    $messageData = [
      'sender_id' => $data['sender']->users_id,
      'receiver_id' => $data['receiver']->users_id,
      'message' => $data['message'],
      'exchange_id' => $data['exchange_id'] ?? null,
      'timestamp' => time() * 1000,
      'status' => 'sent',
      'sender_data' => [
          'users_id' => $data['sender']->users_id,
          'fullname' => $data['sender']->fullname,
          'avatar' => $data['sender']->avatar ?? null,
      ],
      'receiver_data' => [
          'users_id' => $data['receiver']->users_id,
          'fullname' => $data['receiver']->fullname,
          'avatar' => $data['receiver']->avatar ?? null,
      ],
    ];  

    
    return $chatRef->push($messageData);
  }

  /**
   * Update chat summary information in Firebase
   *
   * @param string|int $senderId ID of the sender
   * @param string|int $receiverId ID of the receiver
   * @param string $lastMessage The content of the last message
   * @param int $timestamp Timestamp of the last message
   * @param string|int|null $exchangeId ID of the exchange (optional)
   * @return bool Status of success
   */
  public function updateChatSummary($senderId, $receiverId, $lastMessage, $timestamp, $exchangeId = null)
  {
      try {
          $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);
          $summaryRef = $this->database->getReference('chat_summaries/' . $chatKey);

          $summaryData = [
              'last_message' => $lastMessage,
              'last_message_timestamp' => $timestamp,
              'sender_id' => $senderId,
              'receiver_id' => $receiverId,
              'exchange_id' => $exchangeId,
          ];
          
          $currentSummary = $summaryRef->getValue();
          $unreadCount = 0;
          if ($currentSummary && isset($currentSummary['unread_message_count'])) {
              $unreadCount = $currentSummary['unread_message_count'];
          }
          $summaryData['unread_message_count'] = $unreadCount + 1;

          $summaryRef->update($summaryData);
          Log::info('Chat summary updated for chat: ' . $chatKey);
          return true;
      } catch (FirebaseException $e) {
          Log::error('Firebase update chat summary error: ' . $e->getMessage());
          return false;
      }
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
      
      $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);
      $chatRef = $this->database->getReference('chats/' . $chatKey);

      
      $specificMessageRef = $this->database->getReference('chats/' . $chatKey . '/' . $messageId);
      $specificMessage = $specificMessageRef->getValue();

      if ($specificMessage) {
        
        $specificMessageRef->update(['status' => $status]);

        
        if ($status === 'read') {
            $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);
            $summaryRef = $this->database->getReference('chat_summaries/' . $chatKey);
            $currentSummary = $summaryRef->getValue();
            if ($currentSummary && isset($currentSummary['unread_message_count']) && $currentSummary['unread_message_count'] > 0) {
                $summaryRef->update(['unread_message_count' => $currentSummary['unread_message_count'] - 1]);
                Log::info('Unread message count decremented for chat: ' . $chatKey);
            }
        }

        Log::info("Message $messageId status updated to $status directly");
        return true;
      }

      
      $messages = $chatRef->getValue();
      if ($messages) {
        foreach ($messages as $key => $msg) {
          
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
    
    $ids = [(string)$userId1, (string)$userId2];
    sort($ids);
    $baseKey = implode('_', $ids);

    
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
      
      $userStatusRef = $this->database->getReference('user_status/' . $userId);
      $userStatus = $userStatusRef->getValue();

      if (!$userStatus) {
        return false; 
      }

      
      if (!empty($userStatus['active_chat'])) {
        $activeChat = $userStatus['active_chat'];

        
        if ($exchangeId) {
          return $activeChat['user_id'] == $otherUserId && $activeChat['exchange_id'] == $exchangeId;
        }

        
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
