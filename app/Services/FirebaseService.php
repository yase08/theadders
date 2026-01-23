<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\WebPushConfig;
use Kreait\Firebase\Database\Transaction;
use Kreait\Firebase\Database;
use Kreait\Firebase\Exception\Messaging\NotFound;

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

    public function updateLatestProductTimestamp(): void
    {
        try {
            $this->database
                ->getReference('global_stats/latest_product_timestamp')
                ->set(Database::SERVER_TIMESTAMP);
            Log::info('Updated latest_product_timestamp.');
        } catch (\Exception $e) {
            Log::error('Failed to update latest_product_timestamp: ' . $e->getMessage());
        }
    }

    public function clearNewProductStatus(int $userId): void
    {
        try {
            $this->database
                ->getReference('user_notifications/' . $userId . '/last_cleared_products_timestamp')
                ->set(Database::SERVER_TIMESTAMP);
            Log::info('Updated last_cleared_products_timestamp for user: ' . $userId);
        } catch (\Exception $e) {
            Log::error('Failed to update last_cleared_products_timestamp for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    public function incrementNewExchangeCount(int $userId): void
    {
        try {
            $this->database
                ->getReference('user_notifications/' . $userId . '/new_exchange_requests')
                ->set([
                    '.sv' => ['increment' => 1]
                ]);
            Log::info('Incremented new exchange request count for user: ' . $userId);
        } catch (\Exception $e) {
            Log::error('Failed to increment new exchange request count for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    public function resetNewExchangeCount(int $userId): void
    {
        try {
            $this->database
                ->getReference('user_notifications/' . $userId . '/new_exchange_requests')
                ->set(0);
            Log::info('Reset new exchange request count for user: ' . $userId);
        } catch (\Exception $e) {
            Log::error('Failed to reset new exchange request count for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    public function sendMessage($data, $shouldSendNotification = true)
    {
        try {
            $this->updateChatMetadata($data);

            if ($shouldSendNotification && !empty($data['receiver']->fcm_token)) {
                $this->saveNotification(
                    $data['receiver']->users_id,
                    'New message from ' . $data['sender']->fullname,
                    substr($data['message'], 0, 100) . (strlen($data['message']) > 100 ? '...' : ''),
                    [
                        'exchange_id' => $data['exchange_id'] ?? '',
                        'sender_id' => (string)$data['sender']->users_id,
                        'message_id' => $data['message_id'] ?? '',
                        'type' => 'chat_message',
                        'room_id' => $data['room_id'] ?? '',
                        'priority' => $data['priority'] ?? 'normal',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'user_id' => $data['receiver']->users_id
                    ]
                );

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
                            'message_id' => $data['message_id'] ?? '',
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

    public function updateHasRatedStatus(int $userId, string $chatKey): void
    {
        try {
            $this->database
                ->getReference('chat_rooms/' . $userId . '/' . $chatKey . '/has_rated')
                ->set(true);
            Log::info("Updated has_rated for user {$userId} in chat {$chatKey}");
        } catch (\Exception $e) {
            Log::error("Failed to update has_rated status: " . $e->getMessage());
        }
    }

    public function removeChatRoom(int $userId1, int $userId2, string $chatKey): void
    {
        try {
            $updates = [
                'chat_rooms/' . $userId1 . '/' . $chatKey => null,
                'chat_rooms/' . $userId2 . '/' . $chatKey => null,
            ];
            $this->database->getReference()->update($updates);
            Log::info("Removed chat room {$chatKey} for users {$userId1} and {$userId2}");
        } catch (\Exception $e) {
            Log::error("Failed to remove chat room: " . $e->getMessage());
        }
    }

    public function createChatRoom(\App\Models\Exchange $exchange)
    {
        try {

            $exchange->load(['requester', 'receiver', 'requesterProduct', 'receiverProduct']);

            $requester = $exchange->requester;
            $receiver = $exchange->receiver;
            $chatKey = $this->getChatKey($requester->users_id, $receiver->users_id, $exchange->exchange_id);


            $roomDataForRequester = [
                'exchange_id'           => $exchange->exchange_id,
                'status'                => 'Approve',
                'requester_confirmed'   => $exchange->requester_confirmed ?? false,
                'receiver_confirmed'    => $exchange->receiver_confirmed ?? false,
                'user'                  => [
                    'users_id'  => $receiver->users_id,
                    'fullname'  => $receiver->fullname,
                    'avatar'    => $receiver->avatar,
                ],
                'last_message'          => 'Belum ada pesan',
                'timestamp'             => now()->timestamp * 1000,
                'unread_count'          => 0,
                'has_rated'             => null,
                'requester_product'     => $exchange->requesterProduct,
                'receiver_product'      => $exchange->receiverProduct,
            ];


            $roomDataForReceiver = [
                'exchange_id'           => $exchange->exchange_id,
                'status'                => 'Approve',
                'requester_confirmed'   => $exchange->requester_confirmed ?? false,
                'receiver_confirmed'    => $exchange->receiver_confirmed ?? false,
                'user'                  => [
                    'users_id'  => $requester->users_id,
                    'fullname'  => $requester->fullname,
                    'avatar'    => $requester->avatar,
                ],
                'last_message'          => 'Belum ada pesan',
                'timestamp'             => now()->timestamp * 1000,
                'unread_count'          => 0,
                'has_rated'             => null,
                'requester_product'     => $exchange->requesterProduct,
                'receiver_product'      => $exchange->receiverProduct,
            ];


            $updates = [
                'chat_rooms/' . $requester->users_id . '/' . $chatKey => $roomDataForRequester,
                'chat_rooms/' . $receiver->users_id . '/' . $chatKey  => $roomDataForReceiver,
            ];

            Log::debug('Firebase updates: ', $updates);

            $this->database->getReference()->update($updates);
            Log::info('Chat room created in Firebase for exchange: ' . $exchange->exchange_id);
        } catch (\Exception $e) {
            Log::error('Failed to create chat room in Firebase: ' . $e->getMessage());
        }
    }

    public function updateChatRoomRatingStatus(int $raterId, int $ratedId, string $chatKey): void
    {
        try {
            $updates = [
                'chat_rooms/' . $raterId . '/' . $chatKey . '/has_rated' => true,
                'chat_rooms/' . $ratedId . '/' . $chatKey . '/has_rated' => false,
            ];
            $this->database->getReference()->update($updates);
            Log::info("Updated has_rated for rater {$raterId} (true) and rated {$ratedId} (false) in chat {$chatKey}");
        } catch (\Exception $e) {
            Log::error("Failed to update chat room rating status: " . $e->getMessage());
        }
    }

    public function updateChatRoomConfirmationStatus(\App\Models\Exchange $exchange): void
    {
        try {
            $requesterId = $exchange->user_id;
            $receiverId = $exchange->to_user_id;
            $chatKey = $this->getChatKey($requesterId, $receiverId, $exchange->exchange_id);

            $updates = [
                'chat_rooms/' . $requesterId . '/' . $chatKey . '/requester_confirmed' => (bool) $exchange->requester_confirmed,
                'chat_rooms/' . $requesterId . '/' . $chatKey . '/receiver_confirmed' => (bool) $exchange->receiver_confirmed,
                'chat_rooms/' . $requesterId . '/' . $chatKey . '/status' => $exchange->status,
                'chat_rooms/' . $receiverId . '/' . $chatKey . '/requester_confirmed' => (bool) $exchange->requester_confirmed,
                'chat_rooms/' . $receiverId . '/' . $chatKey . '/receiver_confirmed' => (bool) $exchange->receiver_confirmed,
                'chat_rooms/' . $receiverId . '/' . $chatKey . '/status' => $exchange->status,
            ];

            $this->database->getReference()->update($updates);
            Log::info("Updated confirmation status in Firebase for exchange: " . $exchange->exchange_id);
        } catch (\Exception $e) {
            Log::error("Failed to update chat room confirmation status: " . $e->getMessage());
        }
    }

    public function storeMessage($data)
    {
        $chatKey = $this->getChatKey($data['sender']->users_id, $data['receiver']->users_id, $data['exchange_id'] ?? null);

        $senderId = $data['sender']->users_id;
        $receiverId = $data['receiver']->users_id;

        $membersRef = $this->database->getReference('chats/' . $chatKey . '/members');
        $membersRef->set([
            $senderId => true,
            $receiverId => true,
        ]);

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


    public function updateChatMetadata($data)
    {
        $senderId = $data['sender']->users_id;
        $receiverId = $data['receiver']->users_id;
        $exchangeId = $data['exchange_id'] ?? null;
        $chatKey = $this->getChatKey($senderId, $receiverId, $exchangeId);

        $timestamp = Database::SERVER_TIMESTAMP;

        $updates = [
            'chats/' . $chatKey . '/metadata/last_message' => $data['message'],
            'chats/' . $chatKey . '/metadata/last_message_timestamp' => $timestamp,
            'chats/' . $chatKey . '/metadata/sender_id' => $senderId,
            'chats/' . $chatKey . '/metadata/unread_message_count' => ['.sv' => ['increment' => 1]],

            'chat_rooms/' . $senderId . '/' . $chatKey . '/last_message' => $data['message'],
            'chat_rooms/' . $senderId . '/' . $chatKey . '/timestamp' => $timestamp,
            'chat_rooms/' . $senderId . '/' . $chatKey . '/unread_count' => 0,

            'chat_rooms/' . $receiverId . '/' . $chatKey . '/last_message' => $data['message'],
            'chat_rooms/' . $receiverId . '/' . $chatKey . '/timestamp' => $timestamp,
            'chat_rooms/' . $receiverId . '/' . $chatKey . '/unread_count' => ['.sv' => ['increment' => 1]],
        ];

        try {
            $this->database->getReference()->update($updates);
            Log::info('Chat metadata and chat rooms updated for chat: ' . $chatKey);
        } catch (\Exception $e) {
            Log::error('Failed to perform multi-path update for chat metadata: ' . $e->getMessage());
        }
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

                    $this->database->runTransaction(function (Transaction $transaction) use ($metadataRef) {
                        $currentData = $transaction->snapshot($metadataRef)->getValue();

                        if ($currentData && isset($currentData['unread_message_count']) && $currentData['unread_message_count'] > 0) {
                            $currentData['unread_message_count'] = 0;
                            $transaction->set($metadataRef, $currentData);
                        }
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


    public function getChatKey($userId1, $userId2, $exchangeId = null)
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
        if (isset($data['user_id'])) {
            $this->saveNotification($data['user_id'], $title, $body, $data);
        }

        if (empty($token)) {
            Log::warning('Attempted to send notification but token was empty.');
            return ['success' => false, 'message' => 'Token was empty.'];
        }
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
        } catch (NotFound $e) {
            Log::warning("FCM token not found, deleting from DB: " . $token);

            $user = \App\Models\User::where('fcm_token', $token)->first();
            if ($user) {
                $user->update(['fcm_token' => null]);
            }
            return ['success' => false, 'message' => 'Token not found and has been deleted.'];
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

        $results = [];

        foreach ($chatKeys as $key) {
            try {
                $value = $this->database
                    ->getReference('chats/' . $key . '/metadata')
                    ->getValue();

                $results[$key] = $value;
            } catch (\Exception $e) {
                Log::warning("Failed to fetch metadata for chat key {$key}: " . $e->getMessage());
                $results[$key] = null;
            }
        }

        return $results;
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

            $this->syncUnreadNotificationCount($userId);
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

    public function syncUnreadNotificationCount(int $userId): void
    {
        try {
            $count = \App\Models\Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count();

            $this->database
                ->getReference('user_notifications/' . $userId . '/unread_notifications_count')
                ->set($count);

            Log::info("Synced unread notification count for user {$userId} to {$count}");
        } catch (\Exception $e) {
            Log::error("Failed to sync unread notification count for user {$userId}: " . $e->getMessage());
        }
    }

    public function deleteChatByExchange(\App\Models\Exchange $exchange): void
    {
        try {
            $userId1 = $exchange->user_id;
            $userId2 = $exchange->to_user_id;
            $exchangeId = $exchange->exchange_id;

            $chatKey = $this->getChatKey($userId1, $userId2, $exchangeId);

            $updates = [
                'chat_rooms/' . $userId1 . '/' . $chatKey => null,
                'chat_rooms/' . $userId2 . '/' . $chatKey => null,
                'chats/' . $chatKey => null,
            ];

            $this->database->getReference()->update($updates);

            Log::info("Chat Firebase deleted for cancelled exchange {$exchangeId}");
        } catch (\Exception $e) {
            Log::error("Failed to delete chat for exchange {$exchangeId}: " . $e->getMessage());
        }
    }

}
