<?php

namespace App\Repositories;

use App\Interfaces\RatingInterface;
use App\Models\UserRating;
use App\Models\Exchange;

class RatingRepository implements RatingInterface
{
    public function rateExchangeUser(array $data)
    {
        try {
            // Verify exchange completion
            $exchange = Exchange::where('exchange_id', $data['exchange_id'])->first();

            if (!$exchange) {
                throw new \Exception('Exchange not found.');
            }

            if ($exchange->status !== 'Completed') {
                throw new \Exception('Exchange must be completed to be rated.');
            }

            // Verify user is part of the exchange
            $raterUserId = auth()->id();
            if ($raterUserId !== $exchange->user_id && $raterUserId !== $exchange->to_user_id) {
                throw new \Exception('Unauthorized to rate this exchange');
            }

            // Determine which user to rate
            $ratedUserId = $data['rated_user_id'];
            
            // Verify the rated user is part of the exchange
            if ($ratedUserId != $exchange->user_id && $ratedUserId != $exchange->to_user_id) {
                throw new \Exception('Invalid user for this exchange');
            }

            // Verify user is not rating themselves
            if ($raterUserId === $ratedUserId) {
                throw new \Exception('Cannot rate yourself');
            }

            // Check if user has already rated this user in this exchange
            $existingRating = UserRating::where('rated_user_id', $ratedUserId)
                ->where('rater_user_id', $raterUserId)
                ->where('exchange_id', $data['exchange_id'])
                ->where('status', 1)
                ->exists();

            if ($existingRating) {
                throw new \Exception('You have already rated this user in this exchange');
            }

            return UserRating::create([
                'rated_user_id' => $ratedUserId,
                'rater_user_id' => $raterUserId,
                'rating' => $data['rating'],
                'exchange_id' => $data['exchange_id'],
                'created' => now(),
                'author' => $raterUserId,
                'status' => 1
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Unable to rate user: ' . $e->getMessage());
        }
    }

    public function getUserRatings(int $userId)
    {
        try {
            $ratings = UserRating::where('rated_user_id', $userId)
                ->where('status', 1)
                ->with(['rater', 'exchange'])
                ->get();

            $averageRating = $ratings->avg('rating');
            $totalRatings = $ratings->count();

            return [
                'ratings' => $ratings,
                'average_rating' => round($averageRating, 1),
                'total_ratings' => $totalRatings
            ];
        } catch (\Exception $e) {
            throw new \Exception('Unable to get user ratings: ' . $e->getMessage());
        }
    }

    public function getGivenRatings(int $userId)
    {
        try {
            return UserRating::where('rater_user_id', $userId)
                ->where('status', 1)
                ->with(['ratedUser', 'exchange'])
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Unable to get given ratings: ' . $e->getMessage());
        }
    }
}