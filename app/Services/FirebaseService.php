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
  protected $messaging;
  protected $firebase;
  protected $database;

  public function __construct()
  {
    $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'))->withDatabaseUri(config('services.firebase.database_url'));
    $this->messaging = $factory->createMessaging();
    $this->database = $factory->createDatabase();
  }

  public function sendMessage($data)
  {
    try {
      $this->storeMessage($data);

      if ($data['receiver']->fcm_token) {
        $message = CloudMessage::withTarget('token', $data['receiver']->fcm_token)
          ->withNotification(Notification::create(
            'Pesan baru dari ' . $data['sender']->fullname,
            substr($data['message'], 0, 100) . (strlen($data['message']) > 100 ? '...' : '')
          ))
          ->withData([
            'exchange_id' => $data['exchange_id'] ?? '',
            'sender_id' => (string)$data['sender']->users_id,
            'type' => 'chat_message'
          ]);

        $this->messaging->send($message);
      }

      return true;
    } catch (\Exception $e) {
      \Log::error('Firebase error: ' . $e->getMessage());
      return false;
    }
  }

  private function storeMessage($data)
  {
    $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id);
    $chatRef = $this->database->getReference('chats/' . $chatKey);

    $messageData = [
      'sender_id' => $data['sender']->users_id,
      'receiver_id' => $data['receiver']->users_id,
      'message' => $data['message'],
      'exchange_id' => $data['exchange_id'] ?? null,
      'timestamp' => time() * 1000,
      'status' => 'sent'
    ];

    // Use push() directly on the reference
    $chatRef->push($messageData);
  }

  public function updateFirebaseMessageStatus($messageId, $senderId, $receiverId, $status)
  {
    try {
      $chatKey = $this->getChatKey($senderId, $receiverId);
      $chatRef = $this->database->getReference('chats/' . $chatKey);

      $messages = $chatRef->getValue();
      if ($messages) {
        foreach ($messages as $key => $msg) {
          if ($msg['message_id'] == $messageId) {
            // Use a direct reference to the child node instead of getChild()
            $this->database->getReference('chats/' . $chatKey . '/' . $key)
              ->update(['status' => $status]);
          }
        }
      }
    } catch (\Exception $e) {
      Log::error('Firebase update status error: ' . $e->getMessage());
    }
  }

  private function getChatKey($senderId, $receiverId)
  {
    // Create consistent chat key regardless of who is sender/receiver
    $ids = [$senderId, $receiverId];
    sort($ids);
    return implode('_', $ids);
  }

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
}
