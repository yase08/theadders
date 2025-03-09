<?php

namespace App\Repositories;

use App\Interfaces\ExchangeInterface;
use App\Models\Exchange;

class ExchangeRepository implements ExchangeInterface
{
  public function requestExchange(array $data)
  {
    try {
      $exchange = Exchange::create([
        'product_id'   => $data['product_id'],
        'to_product_id' => $data['to_product_id'],
        'user_id'      => auth()->id(),
        'to_user_id'   => $data['to_user_id'],
        'status'       => 'Submission',
        'author' => 'system'
      ]);

      return $exchange;
    } catch (\Exception $e) {
      throw new \Exception('Unable to request exchange: ' . $e->getMessage());
    }
  }


  public function approveExchange(int $exchangeId)
  {
    try {
      $exchange = Exchange::findOrFail($exchangeId);


      if ($exchange->to_user_id !== auth()->id()) {
        throw new \Exception('Unauthorized action.', 403);
      }

      $exchange->update(['status' => 'Approve']);

      return $exchange;
    } catch (\Exception $e) {
      throw new \Exception('Unable to approve exchange: ' . $e->getMessage());
    }
  }


  public function declineExchange(int $exchangeId)
  {
    try {
      $exchange = Exchange::findOrFail($exchangeId);

      if ($exchange->to_user_id !== auth()->id()) {
        throw new \Exception('Unauthorized action.', 403);
      }

      $exchange->update(['status' => 'Not Approve']);

      return $exchange;
    } catch (\Exception $e) {
      throw new \Exception('Unable to decline exchange: ' . $e->getMessage());
    }
  }


  public function getUserExchanges()
  {
    try {
      $userId = auth()->id();

      $exchanges = Exchange::where('user_id', $userId)
        ->orWhere('to_user_id', $userId)
        ->orderBy('created', 'desc')
        ->with(['requesterProduct', 'receiverProduct']) // Tambahkan relasi produk
        ->get();

      return $exchanges;
    } catch (\Exception $e) {
      throw new \Exception('Unable to get user request exchange: ' . $e->getMessage());
    }
  }


  public function getExchangeById(int $exchangeId)
  {
    try {
      $exchange = Exchange::findOrFail($exchangeId);

      return $exchange;
    } catch (\Exception $e) {
      throw new \Exception('Unable to get exchange by id: ' . $e->getMessage());
    }
  }
}
