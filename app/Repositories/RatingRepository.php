<?php

namespace App\Repositories;

use App\Interfaces\RatingInterface;
use App\Models\ProductRating;
use App\Models\Exchange;

class RatingRepository implements RatingInterface
{
    public function rateExchangeProduct(array $data)
    {
        try {
            // Verify exchange completion
            $exchange = Exchange::where('exchange_id', $data['exchange_id'])
                ->where('status', 'Completed')
                ->firstOrFail();

            // Verify user is part of the exchange
            $userId = auth()->id();
            if ($userId !== $exchange->user_id && $userId !== $exchange->to_user_id) {
                throw new \Exception('Unauthorized to rate this exchange');
            }

            // Determine which product to rate based on who's rating and the target product
            $productToRate = $data['target_product_id'];
            
            // Verify the product belongs to the exchange
            if ($productToRate != $exchange->product_id && $productToRate != $exchange->to_product_id) {
                throw new \Exception('Invalid product for this exchange');
            }

            // Verify user is not rating their own product
            if (($userId === $exchange->user_id && $productToRate === $exchange->product_id) ||
                ($userId === $exchange->to_user_id && $productToRate === $exchange->to_product_id)) {
                throw new \Exception('Cannot rate your own product');
            }

            // Check if user has already rated this product in this exchange
            $existingRating = ProductRating::where('product_id', $productToRate)
                ->where('user_id', $userId)
                ->where('exchange_id', $data['exchange_id'])
                ->where('status', 1)
                ->exists();

            if ($existingRating) {
                throw new \Exception('You have already rated this product in this exchange');
            }

            return ProductRating::create([
                'product_id' => $productToRate,
                'user_id' => $userId,
                'rating' => $data['rating'],
                'exchange_id' => $data['exchange_id'],
                'created' => now(),
                'author' => auth()->id(),
                'status' => 1
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Unable to rate product: ' . $e->getMessage());
        }
    }

    public function getProductRatings(int $productId)
    {
        try {
            $ratings = ProductRating::where('product_id', $productId)
                ->where('status', 1)
                ->with('user')
                ->get();

            $averageRating = $ratings->avg('rating');
            $totalRatings = $ratings->count();

            return [
                'ratings' => $ratings,
                'average_rating' => round($averageRating, 1),
                'total_ratings' => $totalRatings
            ];
        } catch (\Exception $e) {
            throw new \Exception('Unable to get product ratings: ' . $e->getMessage());
        }
    }

    public function getUserRatings(int $userId)
    {
        try {
            return ProductRating::where('user_id', $userId)
                ->where('status', 1)
                ->with('product')
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Unable to get user ratings: ' . $e->getMessage());
        }
    }
}