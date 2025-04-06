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

            // Determine which product to rate based on who's rating
            $productToRate = $userId === $exchange->user_id 
                ? $exchange->to_product_id 
                : $exchange->product_id;

            // Check if user has already rated this product in this exchange
            $existingRating = ProductRating::where('product_id', $productToRate)
                ->where('user_id', $userId)
                ->where('status', 1)
                ->exists();

            if ($existingRating) {
                throw new \Exception('You have already rated this product');
            }

            return ProductRating::create([
                'product_id' => $productToRate,
                'user_id' => $userId,
                'rating' => $data['rating'],
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