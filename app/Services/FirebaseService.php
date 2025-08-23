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
use Kreait\Firebase\Database\Transaction;

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

    public function sendMessage($data, $shouldSendNotification = true)
    {
        try {
           
            $messageRef = $this->storeMessage($data);
            
           
            $this->updateChatMetadata($data);

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
                    $this->saveNotification(
                        $data['receiver']->users_id,
                        'New message from ' . $data['sender']->fullname,
                        substr($data['message'], 0, 100) . (strlen($data['message']) > 100 ? '...' : ''),
                        [
                            'exchange_id' => $data['exchange_id'] ?? '',
                            'sender_id' => (string)$data['sender']->users_id,
                            'message_id' => $messageRef->getKey() ?? '',
                            'type' => 'chat_message',
                            'room_id' => $data['room_id'] ?? '',
                            'priority' => $data['priority'] ?? 'normal',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'user_id' => $data['receiver']->users_id
                        ]
                    );
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

    public function createChatRoom(Exchange $exchange)
    {
        try {
            
            $exchange->load(['requester', 'receiver', 'requesterProduct', 'receiverProduct']);

            $requester = $exchange->requester;
            $receiver = $exchange->receiver;
            $chatKey = $this->getChatKey($requester->users_id, $receiver->users_id, $exchange->exchange_id);
            
            
            $roomDataForRequester = [
                'exchange_id'       => $exchange->exchange_id,
                'status'            => 'Approve',
                'user'              => [
                    'users_id'  => $receiver->users_id,
                    'fullname'  => $receiver->fullname,
                    'avatar'    => $receiver->avatar,
                ],
                'last_message'      => 'Belum ada pesan',
                'timestamp'         => now()->timestamp * 1000,
                'unread_count'      => 0,
                'has_rated'         => false,
                'requester_product' => $exchange->requesterProduct,
                'receiver_product'  => $exchange->receiverProduct,
            ];

            
            $roomDataForReceiver = [
                'exchange_id'       => $exchange->exchange_id,
                'status'            => 'Approve',
                'user'              => [
                    'users_id'  => $requester->users_id,
                    'fullname'  => $requester->fullname,
                    'avatar'    => $requester->avatar,
                ],
                'last_message'      => 'Belum ada pesan',
                'timestamp'         => now()->timestamp * 1000,
                'unread_count'      => 0,
                'has_rated'         => false,
                'requester_product' => $exchange->requesterProduct,
                'receiver_product'  => $exchange->receiverProduct,
            ];

            
            $updates = [
                'chat_rooms/' . $requester->users_id . '/' . $chatKey => $roomDataForRequester,
                'chat_rooms/' . $receiver->users_id . '/' . $chatKey  => $roomDataForReceiver,
            ];

            $this->database->getReference()->update($updates);
            Log::info('Chat room created in Firebase for exchange: ' . $exchange->exchange_id);

        } catch (\Exception $e) {
            Log::error('Failed to create chat room in Firebase: ' . $e->getMessage());
        }
    }


    private function storeMessage($data)
    {
        $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id, $data['exchange_id'] ?? null);
       
        $messagesRef = $this->database->getReference('chats/' . $chatKey . '/messages');

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

        return $messagesRef->push($messageData);
    }

    private function updateChatMetadata($data)
    {
        $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id, $data['exchange_id'] ?? null);
        $metadataRef = $this->database->getReference('chats/' . $chatKey . '/metadata');
        
        $metadataRef->runTransaction(function (&$currentData) use ($data) {
            $unreadCount = ($currentData['unread_message_count'] ?? 0) + 1;

            $currentData = [
                'last_message' => $data['message'],
                'last_message_timestamp' => time() * 1000,
                'sender_id' => $data['sender']->users_id,
                'receiver_id' => $data['receiver']->users_id,
                'exchange_id' => $data['exchange_id'] ?? null,
                'unread_message_count' => $unreadCount,
               
                'participants' => [
                    (string)$data['sender']->users_id => [
                        'fullname' => $data['sender']->fullname,
                        'avatar' => $data['sender']->avatar ?? null,
                    ],
                    (string)$data['receiver']->users_id => [
                        'fullname' => $data['receiver']->fullname,
                        'avatar' => $data['receiver']->avatar ?? null,
                    ]
                ]
            ];
            
            return $currentData;
        });
        
        Log::info('Chat metadata updated for chat: ' . $chatKey);
    }

    public function updateFirebaseMessageStatus($messageId, $senderId, $receiverId, $status, $exchangeId = null)
    {
        try {
            $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);
            
           
            $specificMessageRef = $this->database->getReference('chats/' . $chatKey . '/messages/' . $messageId);
            $specificMessage = $specificMessageRef->getValue();

            if ($specificMessage) {
                $specificMessageRef->update(['status' => $status]);

                if ($status === 'read') {
                   
                    $metadataRef = $this->database->getReference('chats/' . $chatKey . '/metadata');
                    $metadataRef->runTransaction(function(&$currentData) {
                        if ($currentData && isset($currentData['unread_message_count']) && $currentData['unread_message_count'] > 0) {
                            
                            $currentData['unread_message_count'] = 0;
                        }
                        return $currentData;
                    });
                    Log::info('Unread message count reset for chat: ' . $chatKey);
                }

                Log::info("Message $messageId status updated to $status directly");
                return true;
            }

            Log::warning("Message $messageId not found in chat $chatKey");
            return false;
        } catch (\Exception $e) {
            Log::error('Firebase update status error: ' . $e->getMessage());
            return false;
        }
    }


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

            if (isset($data['user_id'])) {
                $this->saveNotification($data['user_id'], $title, $body, $data);
            }

            return ['success' => true, 'message' => 'Notification send successfully'];
        } catch (MessagingException $e) {
            Log::error('MessagingException: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getChatsMetadata(array $chatKeys): array
    {
        if (empty($chatKeys)) {
            return [];
        }

        $references = [];
        foreach ($chatKeys as $key) {
            $references[$key] = $this->database->getReference('chats/' . $key . '/metadata');
        }

        try {
            $snapshot = $this->database->fetch($references);
            $results = [];
            foreach ($snapshot as $key => $value) {
                $results[$key] = $value;
            }
            return $results;
        } catch (\Exception $e) {
            Log::error('Failed to fetch multiple chat metadata: ' . $e->getMessage());
            return [];
        }
    }


    public function saveNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            \App\Models\Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            Log::info('Notification saved to database for user: ' . $userId);
        } catch (\Exception $e) {
            Log::error('Failed to save notification to database: ' . $e->getMessage());
        }
    }

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

    public function clearAllMessages(): void
    {
        $this->database->getReference('chats')->remove();
    }
}