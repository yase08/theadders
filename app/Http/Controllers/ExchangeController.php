<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeRequest;
use App\Interfaces\ExchangeInterface;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;



class ExchangeController extends Controller
{
  protected $firebaseService;
  private ExchangeInterface $exchangeInterface;

  public function __construct(ExchangeInterface $exchangeInterface, FirebaseService $firebaseService)
  {
    $this->firebaseService = $firebaseService;
    $this->exchangeInterface = $exchangeInterface;
  }

  public function requestExchange(ExchangeRequest $request)
  {
    try {
      DB::beginTransaction();

      $validatedData = $request->validated();

      $exchange = $this->exchangeInterface->requestExchange($validatedData);

      DB::commit();

      $receiver = User::find($exchange->to_user_id);

      if (!$receiver) {
        \Log::error('Penyewa tidak ditemukan.');
        return response()->json(['message' => 'Receiver not found'], 404);
      }

      \Log::info('User yang login:', ['user' => auth()->user()]);

      \Log::info('fcm_token: ', ['token' => $receiver->fcm_token]);

      \Log::info('message: ', ['ini' => "Kamu mendapatkan permintaan exchange baru dari " . auth()->user()->fullname]);

      if ($receiver && $receiver->fcm_token && $receiver->id != auth()->id()) {
        \Log::info('notif terkirim: '); 
        $this->firebaseService->sendNotification(
          $receiver->fcm_token,
          "Exchange Request",
          "You have received a new exchange request from " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request',
            'user_id' => $receiver->users_id,
          ]
        );
      }

      if ($receiver) {
        $this->firebaseService->incrementNewExchangeCount($receiver->users_id);
      }

      return response()->json([
        'message' => 'success',
        'exchange' => $exchange
      ], 201);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function readIncomingExchanges()
  {
    try {
      $this->firebaseService->resetNewExchangeCount(auth()->id());

      return response()->json([
        'message' => 'success',
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }


  public function approveExchange(int $exchangeId)
  {
    try {
      DB::beginTransaction();

      $exchange = $this->exchangeInterface->approveExchange($exchangeId);

      DB::commit();

      $requester = User::find($exchange->user_id);

      if ($requester && $requester->fcm_token) {
        $this->firebaseService->sendNotification(
          $requester->fcm_token,
          "Exchange Approved",
          "Your exchange request has been approved by " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request',
            'user_id' => $requester->users_id,
          ]
        );
      }

      return response()->json([
        'message' => 'success',
        'exchange' => $exchange
      ], 201);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function declineExchange(int $exchangeId)
  {
    try {
      DB::beginTransaction();

      $exchange = $this->exchangeInterface->declineExchange($exchangeId);

      DB::commit();

      $requester = User::find($exchange->user_id);

      if ($requester && $requester->fcm_token) {
        $this->firebaseService->sendNotification(
          $requester->fcm_token,
          "Exchange Rejected",
          "Your exchange request has been rejected by " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request',
            'user_id' => $requester->users_id,
          ]
        );
      }

      return response()->json([
        'message' => 'success',
        'exchange' => $exchange
      ], 201);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getUserExchanges()
  {
    try {
      $exchanges = $this->exchangeInterface->getUserExchanges();
      return response()->json([
        'message' => 'success',
        'exchanges' => $exchanges
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getExchangeById(int $exchangeId)
  {
    try {
      $exchanges = $this->exchangeInterface->getExchangeById($exchangeId);

      return response()->json([
        'success' => true,
        'data' => $exchanges,
        'message' => 'success'
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getIncomingExchanges(Request $request) 
  {
    try {
      $search = $request->query('search'); 
      $exchanges = $this->exchangeInterface->getIncomingExchanges($search); 
      return response()->json([
        'success' => true,
        'data' => $exchanges,
        'message' => 'success'
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getOutgoingExchanges()
  {
    try {
      $exchanges = $this->exchangeInterface->getOutgoingExchanges();
      return response()->json([
        'success' => true,
        'data' => $exchanges,
        'message' => 'success'
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function completeExchange($exchangeId)
  {
    try {
      DB::beginTransaction();

      $exchange = $this->exchangeInterface->completeExchange($exchangeId);

      DB::commit();

      
      $otherUserId = auth()->id() === $exchange->user_id
        ? $exchange->to_user_id
        : $exchange->user_id;

      $otherUser = User::find($otherUserId);

      if ($otherUser && $otherUser->fcm_token) {
        $this->firebaseService->sendNotification(
          $otherUser->fcm_token,
          "Exchange Completed",
          "The exchange has been completed by " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->exchange_id,
            'type' => 'exchange_completed',
            'user_id' => $otherUser->users_id,
          ]
        );
      }

      return response()->json([
        'message' => 'success',
        'exchange' => $exchange
      ], 200);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function cancelExchange($exchangeId)
  {
    try {
      DB::beginTransaction();

      $exchange = $this->exchangeInterface->cancelExchange($exchangeId);

      DB::commit();

      
      $otherUserId = auth()->id() === $exchange->user_id
        ? $exchange->to_user_id
        : $exchange->user_id;

      $otherUser = User::find($otherUserId);

      if ($otherUser && $otherUser->fcm_token) {
        $this->firebaseService->sendNotification(
          $otherUser->fcm_token,
          "Exchange Cancelled",
          "The exchange has been cancelled by " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->exchange_id,
            'type' => 'exchange_cancelled',
            'user_id' => $otherUser->users_id,
          ]
        );
      }

      return response()->json([
        'message' => 'success',
        'exchange' => $exchange
      ], 200);
    } catch (\Throwable $th) {
      DB::rollBack();
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getProductExchangeRequests($productId)
  {
    try {
      $exchanges = $this->exchangeInterface->getProductExchangeRequests($productId);
      return response()->json([
        'success' => true,
        'data' => $exchanges,
        'message' => 'success'
      ]);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }
}