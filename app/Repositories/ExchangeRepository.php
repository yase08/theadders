<?php

namespace App\Repositories;

use App\Interfaces\ExchangeInterface;
use App\Models\Exchange;
use App\Models\Product;
use App\Services\FirebaseService;

class ExchangeRepository implements ExchangeInterface
{
  protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

  public function requestExchange(array $data)
  {
    try {
      $authUserId = auth()->id();
      $targetUserId = $data['to_user_id'];
      $productId = $data['product_id'];
      $toProductId = $data['to_product_id'];

      $product = Product::find($productId);
      $toProduct = Product::find($toProductId);

      if (!$product || !$toProduct || $product->price !== $toProduct->price) {
        throw new \Exception('Sorry, your item is not available within this price range.');
      }

      $completedExchange = Exchange::where('status', 'Completed')
        ->where(function ($q) use ($productId, $toProductId) {
          $q->whereIn('product_id', [$productId, $toProductId])
            ->orWhereIn('to_product_id', [$productId, $toProductId]);
        })
        ->exists();

      if ($completedExchange) {
        throw new \Exception('One or both products have already been exchanged and cannot be used again.');
      }

      $existingExchange = Exchange::where(function ($query) use ($authUserId, $targetUserId, $productId, $toProductId) {

        $query->where('user_id', $authUserId)
          ->where('to_user_id', $targetUserId)
          ->where('product_id', $productId)
          ->where('to_product_id', $toProductId);
      })
        ->orWhere(function ($query) use ($authUserId, $targetUserId, $productId, $toProductId) {

          $query->where('user_id', $targetUserId)
            ->where('to_user_id', $authUserId)
            ->where('product_id', $toProductId)
            ->where('to_product_id', $productId);
        })
        ->where('status', 'Approve')
        ->first();


      if ($existingExchange) {
        $exchange = $existingExchange;

        \Log::info('Using existing approved exchange: ' . $existingExchange->exchange_id);
      } else {

        $pendingExchange = Exchange::where(function ($query) use ($authUserId, $targetUserId, $productId, $toProductId) {
          $query->where('user_id', $authUserId)
            ->where('to_user_id', $targetUserId)
            ->where('product_id', $productId)
            ->where('to_product_id', $toProductId);
        })
          ->orWhere(function ($query) use ($authUserId, $targetUserId, $productId, $toProductId) {
            $query->where('user_id', $targetUserId)
              ->where('to_user_id', $authUserId)
              ->where('product_id', $toProductId)
              ->where('to_product_id', $productId);
          })
          ->where('status', '!=', 'Approve')
          ->latest()
          ->first();

        if ($pendingExchange) {

          throw new \Exception('There is already a pending exchange request for these products. Please wait for it to be processed.');
        }

        $exchange = Exchange::create([
          'product_id'    => $productId,
          'to_product_id' => $toProductId,
          'user_id'       => $authUserId,
          'to_user_id'    => $targetUserId,
          'status'        => 'Submission',
          'author'        => 'system'
        ]);

        \Log::info('Created new exchange request: ' . $exchange->exchange_id);
      }

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

          $this->firebaseService->createChatRoom($exchange);

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
        ->with(['requesterProduct', 'receiverProduct'])
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

  public function getIncomingExchanges($search = null)
  {
    try {
      $userId = auth()->id();

      $exchanges = Exchange::where('to_user_id', $userId)
        ->where('status', 'Submission')
            ->with([
                'requesterProduct' => function ($query) {
                    $query->withCount('ratings')->withAvg('ratings', 'rating');
                },
                'receiverProduct' => function ($query) {
                    $query->withCount('ratings')->withAvg('ratings', 'rating');
                },
                'requester',
                'receiver'
            ])
        ->when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->whereHas('requesterProduct', function ($q) use ($search) {
                    $q->where('product_name', 'like', '%' . $search . '%');
                })
                ->orWhereHas('receiverProduct', function ($q) use ($search) {
                    $q->where('product_name', 'like', '%' . $search . '%');
                });
            });
        })
        ->orderBy('created', 'desc')
        ->get();

      return $exchanges;
    } catch (\Exception $e) {
      throw new \Exception('Unable to get incoming exchange requests: ' . $e->getMessage());
    }
  }

  public function getOutgoingExchanges()
  {
    try {
      $userId = auth()->id();

      $exchanges = Exchange::where('user_id', $userId)
        ->where('status', 'Submission')
        ->with([
          'requesterProduct',
          'receiverProduct',
          'requester',
          'receiver'
        ])
        ->orderBy('created', 'desc')
        ->get();

      return $exchanges;
    } catch (\Exception $e) {
      throw new \Exception('Unable to get outgoing exchange requests: ' . $e->getMessage());
    }
  }

  public function completeExchange(int $exchangeId)
  {
    try {
      $exchange = Exchange::with(['requesterProduct', 'receiverProduct'])
        ->findOrFail($exchangeId);

      if ($exchange->status !== 'Approve') {
        throw new \Exception('Exchange must be approved first');
      }

      $userId = auth()->id();
      if ($userId !== $exchange->user_id && $userId !== $exchange->to_user_id) {
        throw new \Exception('Unauthorized action');
      }

      $isRequester = ($userId === $exchange->user_id);
      $isReceiver = ($userId === $exchange->to_user_id);

      if ($isRequester && !$exchange->requester_confirmed) {
        $exchange->requester_confirmed = true;
      } elseif ($isReceiver && !$exchange->receiver_confirmed) {
        $exchange->receiver_confirmed = true;
      } else {
        throw new \Exception('You have already confirmed this exchange. Waiting for the other party.');
      }

      $cancelledExchanges = [];

      if ($exchange->requester_confirmed && $exchange->receiver_confirmed) {
        $exchange->status = 'Completed';
        $exchange->completed_at = now();

        $this->firebaseService->updateLatestProductTimestamp();

        $productIds = [$exchange->product_id, $exchange->to_product_id];
        
        $otherExchanges = Exchange::where('exchange_id', '!=', $exchange->exchange_id)
          ->whereIn('status', ['Submission', 'Approve'])
          ->where(function ($q) use ($productIds) {
            $q->whereIn('product_id', $productIds)
              ->orWhereIn('to_product_id', $productIds);
          })
          ->with(['requester', 'receiver', 'requesterProduct', 'receiverProduct'])
          ->get();

        foreach ($otherExchanges as $otherExchange) {
          $otherExchange->update([
            'status' => 'Cancelled',
            'completed_at' => now()
          ]);
          
          $this->firebaseService->deleteChatByExchange($otherExchange);
          $cancelledExchanges[] = $otherExchange;
        }
      }

      $exchange->save();

      $result = $exchange->fresh(['requesterProduct', 'receiverProduct']);
      $result->cancelled_exchanges = $cancelledExchanges;

      return $result;
    } catch (\Exception $e) {
      throw new \Exception('Unable to complete exchange: ' . $e->getMessage());
    }
  }

  public function cancelExchange(int $exchangeId)
  {
    try {
      $exchange = Exchange::with(['requesterProduct', 'receiverProduct'])
        ->findOrFail($exchangeId);

      if ($exchange->status !== 'Approve') {
        throw new \Exception('Exchange must be approved first');
      }

      $userId = auth()->id();
      if ($userId !== $exchange->user_id && $userId !== $exchange->to_user_id) {
        throw new \Exception('Unauthorized action');
      }

      $exchange->update([
        'status' => 'Cancelled',
        'completed_at' => now()
      ]);

      $chatKey = $this->firebaseService->getChatKey(
        $exchange->user_id,
        $exchange->to_user_id,
        $exchange->exchange_id
      );
      $this->firebaseService->removeChatRoom(
        $exchange->user_id,
        $exchange->to_user_id,
        $chatKey
      );

      return $exchange->fresh(['requesterProduct', 'receiverProduct']);
    } catch (\Exception $e) {
      throw new \Exception('Unable to cancel exchange: ' . $e->getMessage());
    }
  }

  public function getProductExchangeRequests(int $productId)
    {
      try {
        $userId = auth()->id();
    
        $exchanges = Exchange::where(function ($query) use ($userId, $productId) {
            $query->where('user_id', $userId)
                  ->where('product_id', $productId);
          })
          ->orWhere(function ($query) use ($userId, $productId) {
            $query->where('to_user_id', $userId)
                  ->where('to_product_id', $productId);
          })
          ->where('status', 'Submission')
          ->with([
            'requesterProduct',
            'receiverProduct',
            'requester',
            'receiver'
          ])
          ->orderBy('created', 'desc')
          ->get();
    
        return $exchanges;
      } catch (\Exception $e) {
        throw new \Exception('Unable to get product exchange requests: ' . $e->getMessage());
      }
    }
}
