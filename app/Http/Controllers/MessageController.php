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
                    $request->status
                );


                return ApiResponseClass::sendResponse($message, 'success', 200);
            }

            return ApiResponseClass::sendResponse(null, 'Invalid status transition', 400);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    public function getChatList(Request $request) // Accept the Request object
    {
        try {
            $currentUserId = auth()->id();
            $search = $request->query('search'); // Get the search parameter

            $exchangeList = Exchange::where(function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId)
                      ->orWhere('to_user_id', $currentUserId);
            })->where('status', 'Approve')
              ->with([
                  'requester' => function ($query) {
                      $query->select('users_id', 'fullname', 'avatar', 'email', 'firebase_uid');
                  },
                  'receiver' => function ($query) {
                      $query->select('users_id', 'fullname', 'avatar', 'email', 'firebase_uid');
                  },
                  'requesterProduct',
                  'receiverProduct'
                ])
              // Add search filter for product names
              ->when($search, function ($query, $search) {
                  $query->where(function ($q) use ($search) {
                      $q->whereHas('requesterProduct', function ($productQuery) use ($search) {
                          $productQuery->where('product_name', 'like', '%' . $search . '%');
                      })
                      ->orWhereHas('receiverProduct', function ($productQuery) use ($search) {
                          $productQuery->where('product_name', 'like', '%' . $search . '%');
                      });
                  });
              })
              ->get();

            $chatList = $exchangeList->map(function ($exchange) use ($currentUserId) {
                $otherUser = $exchange->user_id == $currentUserId ? $exchange->receiver : $exchange->requester;

                if (!$otherUser) {
                    \Log::warning("Other user not found for exchange_id: " . $exchange->exchange_id . " during getChatList for user_id: " . $currentUserId);
                    return null;
                }

                $lastMessage = Message::where('exchange_id', $exchange->exchange_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $otherUserFirebaseUid = null;
                if (isset($otherUser->firebase_uid) && !empty($otherUser->firebase_uid)) {
                    $otherUserFirebaseUid = (string) $otherUser->firebase_uid;
                } else {
                    $otherUserFirebaseUid = (string) $otherUser->users_id;
                    \Log::warning("Firebase UID not found for otherUser (ID Laravel: {$otherUser->users_id}), falling back to Laravel ID for exchange_id: {$exchange->exchange_id}");
                }

                $userOutput = [
                    'firebase_uid' => $otherUserFirebaseUid,
                    'users_id' => $otherUser->users_id,
                    'fullname' => $otherUser->fullname,
                    'avatar' => $otherUser->avatar,
                    'email' => $otherUser->email
                ];

                return [
                    'exchange_id' => $exchange->exchange_id,
                    'user' => $userOutput,
                    'last_message' => $lastMessage ? $lastMessage->message : null,
                    'timestamp' => $lastMessage ? $lastMessage->created_at : null,
                    'requester_product' => $exchange->requesterProduct,
                    'receiver_product' => $exchange->receiverProduct,
                    'unread_count' => 0,
                ];
            })->filter()->values();

            return ApiResponseClass::sendResponse($chatList, 'Chat list retrieved successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Failed to get chat list: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return ApiResponseClass::sendResponse(null, 'Failed to get chat list: ' . $e->getMessage(), 500);
        }
    }
}
