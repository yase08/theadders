<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeRequest;
use App\Interfaces\ExchangeInterface;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;

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
        return response()->json(['message' => 'User tidak ditemukan'], 404);
      }

      \Log::info('User yang login:', ['user' => auth()->user()]);

      \Log::info('mencoba kirim: '); // Log sukses

      \Log::info('fcm_token: ', ['token' => $receiver->fcm_token]);

      \Log::info('message: ', ['ini' => "Kamu mendapatkan permintaan exchange baru dari " . auth()->user()->fullname]);

      if ($receiver && $receiver->fcm_token) {
        \Log::info('notif terkirim: '); // Log sukses
        $this->firebaseService->sendNotification(
          $receiver->fcm_token,
          "Permintaan Exchange",
          "Kamu mendapatkan permintaan exchange baru dari " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request'
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
          "Exchange Disetujui",
          "Permintaan exchange kamu telah disetujui oleh " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request'
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
          "Exchange Ditolak",
          "Permintaan exchange kamu telah ditolak oleh " . auth()->user()->fullname,
          [
            'exchange_id' => $exchange->id,
            'type' => 'exchange_request'
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

  public function getIncomingExchanges()
  {
    try {
      $exchanges = $this->exchangeInterface->getIncomingExchanges();
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
}
