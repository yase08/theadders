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

            // Check if receiver is active in this chat
            $isReceiverActive = $this->firebaseService->isUserActiveInChat(
                $receiver->users_id,
                $sender->users_id,
                $request->exchange_id
            );

            // Send message to Firebase with notification control
            $this->firebaseService->sendMessage([
                'sender' => $sender,
                'receiver' => $receiver,
                'message' => $request->message,
                'exchange_id' => $request->exchange_id,
                'client_status' => $isReceiverActive ? 'chat_open' : 'chat_closed'
            ], !$isReceiverActive); // Only send notification if receiver is not active in chat

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
            // Update user's active chat status in Firebase
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

            // Update status in Firebase
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

    public function getChatList()
    {
        try {
            $userId = auth()->id();

            $exchangeList = Exchange::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('to_user_id', $userId);
            })->where('status', 'Approve')
                ->with(['requester', 'receiver', 'requesterProduct', 'receiverProduct'])
                ->get();

            $chatList = $exchangeList->map(function ($exchange) use ($userId) {
                $otherUser = $exchange->user_id == $userId ? $exchange->receiver : $exchange->requester;
                $lastMessage = Message::where('exchange_id', $exchange->exchange_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                return [
                    'exchange_id' => $exchange->exchange_id,
                    'user' => $otherUser,
                    'last_message' => $lastMessage ? $lastMessage->message : null,
                    'timestamp' => $lastMessage ? $lastMessage->created_at : null,
                    'requester_product' => $exchange->requesterProduct,
                    'receiver_product' => $exchange->receiverProduct
                ];
            });

            return ApiResponseClass::sendResponse($chatList, 'success', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, 'Failed to get chat list: ' . $e->getMessage(), 500);
        }
    }
}
