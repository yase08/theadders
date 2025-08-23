<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use App\Models\Exchange;

class MessageController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,users_id',
            'message' => 'required|string',
            'exchange_id' => 'required|exists:trs_exchange,exchange_id'
        ]);

        try {
            $sender = auth()->user();
            $receiver = User::find($request->receiver_id);
            $exchange = Exchange::find($request->exchange_id);

            if ($exchange->status !== 'Approve') {
                return ApiResponseClass::sendResponse(null, 'Chat not allowed until exchange is accepted', 403);
            }

            $message = Message::create([
                'sender_id' => $sender->users_id,
                'receiver_id' => $receiver->users_id,
                'message' => $request->message,
                'exchange_id' => $request->exchange_id,
                'status' => 'sent'
            ]);

            
            $isReceiverActive = $this->firebaseService->isUserActiveInChat(
                $receiver->users_id,
                $sender->users_id,
                $request->exchange_id
            );

            
            $this->firebaseService->sendMessage([
                'sender' => $sender,
                'receiver' => $receiver,
                'message' => $request->message,
                'exchange_id' => $request->exchange_id,
                'client_status' => $isReceiverActive ? 'chat_open' : 'chat_closed',
                'room_id' => $request->exchange_id,
                'priority' => 'high'
            ], !$isReceiverActive);

            return ApiResponseClass::sendResponse($message, 'success', 201);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    public function getChatHistory(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,users_id',
            'exchange_id' => 'required|exists:trs_exchange,exchange_id'
        ]);

        try {
            
            $this->firebaseService->updateClientStatus(auth()->id(), [
                'status' => 'chat_open',
                'active_chat' => [
                    'user_id' => $request->user_id,
                    'exchange_id' => $request->exchange_id
                ]
            ]);

            $messages = Message::where('exchange_id', $request->exchange_id)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('sender_id', auth()->id())
                            ->where('receiver_id', $request->user_id);
                    })->orWhere(function ($q) use ($request) {
                        $q->where('sender_id', $request->user_id)
                            ->where('receiver_id', auth()->id());
                    });
                })
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get();

            return ApiResponseClass::sendResponse($messages, 'success', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to get chat history: ' . $e->getMessage(), 500);
        }
    }

    public function updateClientStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:app_open,app_closed,chat_open,chat_closed',
            'other_user_id' => 'nullable|exists:users,users_id',
            'exchange_id' => 'nullable|exists:trs_exchange,exchange_id'
        ]);

        try {
            $userId = auth()->id();
            $status = $request->status;
            $otherUserId = $request->other_user_id;
            $exchangeId = $request->exchange_id;

            
            $this->firebaseService->updateClientStatus($userId, [
                'status' => $status,
                'active_chat' => $status === 'chat_open' ? [
                    'user_id' => $otherUserId,
                    'exchange_id' => $exchangeId
                ] : null
            ]);

            return ApiResponseClass::sendResponse(['status' => 'updated'], 'success', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to update client status: ' . $e->getMessage(), 500);
        }
    }

    public function updateMessageStatus(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
            'status' => 'required|in:delivered,read'
        ]);

        try {
            $message = Message::findOrFail($request->message_id);

            if ($message->receiver_id !== auth()->id()) {
                return ApiResponseClass::sendResponse(null, 'Unauthorized', 403);
            }

            if (
                ($message->status === 'sent' && in_array($request->status, ['delivered', 'read'])) ||
                ($message->status === 'delivered' && $request->status === 'read')
            ) {
                $message->update(['status' => $request->status]);

                $this->firebaseService->updateFirebaseMessageStatus(
                    $message->id,
                    $message->sender_id,
                    $message->receiver_id,
                    $request->status,
                    $message->exchange_id
                );

                return ApiResponseClass::sendResponse($message, 'success', 200);
            }

            return ApiResponseClass::sendResponse(null, 'Invalid status transition', 400);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    public function clearAllMessages()
    {
        try {
            $this->firebaseService->clearAllMessages();
            return ApiResponseClass::sendResponse(null, 'All messages cleared successfully', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to clear messages: ' . $e->getMessage(), 500);
        }
    }

    public function getChatList(Request $request)
    {
        try {
            $currentUserId = auth()->id();
            $search = $request->query('search');

            
            $exchangeList = Exchange::where(function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId)
                    ->orWhere('to_user_id', $currentUserId);
            })
        
            ->where(function ($query) {
                $query->where('status', 'Approve')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('status', 'Completed')
                                ->where(function ($ratingQuery) {
                                    $ratingQuery->whereDoesntHave('ratingsGivenByRequester')
                                                ->orWhereDoesntHave('ratingsGivenByReceiver');
                                });
                    });
            })
            
            ->with([
                'requester:users_id,fullname,avatar,email,firebase_uid',
                'receiver:users_id,fullname,avatar,email,firebase_uid',
                'requesterProduct',
                'receiverProduct'
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('requester', function ($userQuery) use ($search) {
                        $userQuery->where('fullname', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('receiver', function ($userQuery) use ($search) {
                        $userQuery->where('fullname', 'like', '%' . $search . '%');
                    });
                });
            })
            ->get();

            if ($exchangeList->isEmpty()) {
                return ApiResponseClass::sendResponse([], 'Chat list retrieved successfully', 200);
            }
            
            $exchangeIds = $exchangeList->pluck('exchange_id')->all();
            $ratingsFromCurrentUser = \App\Models\UserRating::whereIn('exchange_id', $exchangeIds)
                ->where('rater_user_id', $currentUserId)
                ->pluck('exchange_id')
                ->flip();
            
            $chatKeys = $exchangeList->map(function ($exchange) use ($currentUserId) {
                $otherUser = $exchange->user_id == $currentUserId ? $exchange->receiver : $exchange->requester;
                if (!$otherUser) return null;
                return $this->firebaseService->getChatKey($currentUserId, $otherUser->users_id, $exchange->exchange_id);
            })->filter()->values()->all();

            $firebaseMetadata = $this->firebaseService->getChatsMetadata($chatKeys);

            $chatList = $exchangeList->map(function ($exchange) use ($currentUserId, $firebaseMetadata, $allRatings) {
                $otherUser = $exchange->user_id == $currentUserId ? $exchange->receiver : $exchange->requester;
                if (!$otherUser) return null;
                
                $hasRated = isset($ratingsFromCurrentUser[$exchange->exchange_id]);

                $chatKey = $this->firebaseService->getChatKey($currentUserId, $otherUser->users_id, $exchange->exchange_id);
                $metadata = $firebaseMetadata[$chatKey] ?? null;
                
                $lastMessage = $metadata['last_message'] ?? 'Belum ada pesan';
                $timestamp = $metadata['last_message_timestamp'] ?? now()->timestamp * 1000;
                $unreadCount = $metadata['unread_message_count'] ?? 0;
                if (isset($metadata['sender_id']) && $metadata['sender_id'] == $currentUserId) {
                    $unreadCount = 0;
                }

                return [
                    'exchange_id' => $exchange->exchange_id,
                    'status' => $exchange->status,
                    'user' => [
                        'firebase_uid' => (string)($otherUser->firebase_uid ?? $otherUser->users_id),
                        'users_id'     => $otherUser->users_id,
                        'fullname'     => $otherUser->fullname,
                        'avatar'       => $otherUser->avatar,
                        'email'        => $otherUser->email
                    ],
                    'last_message'      => $lastMessage,
                    'timestamp'         => $timestamp,
                    'requester_product' => $exchange->requesterProduct,
                    'receiver_product'  => $exchange->receiverProduct,
                    'unread_count'      => $unreadCount,
                    'has_rated'         => $hasRated,
                ];
            })->filter()->sortByDesc('timestamp')->values();

            return ApiResponseClass::sendResponse($chatList, 'Chat list retrieved successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Failed to get chat list: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return ApiResponseClass::sendResponse(null, 'Failed to get chat list: ' . $e->getMessage(), 500);
        }
    }

    public function getNotifications(): JsonResponse
    {
        try {
            $userId = auth()->user()->users_id;
            $notifications = \App\Models\Notification::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
            return ApiResponseClass::successResponse('Notifications retrieved successfully', $notifications);
        } catch (Exception $e) {
            return ApiResponseClass::throw(false, 'Failed to retrieve notifications', [$e->getMessage()]);
        }
    }
}
