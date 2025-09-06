<?php

namespace App\Repositories;

use App\Interfaces\RatingInterface;
use App\Models\UserRating;
use App\Models\Exchange;
use App\Services\FirebaseService;

class RatingRepository implements RatingInterface
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function rateExchangeUser(array $data)
    {
        try {

            $exchange = Exchange::where('exchange_id', $data['exchange_id'])->first();

            if (!$exchange) {
                throw new \Exception('Exchange not found.');
            }

            if ($exchange->status !== 'Completed') {
                throw new \Exception('Exchange must be completed to be rated.');
            }

            $raterUserId = auth()->id();
            if ($raterUserId !== $exchange->user_id && $raterUserId !== $exchange->to_user_id) {
                throw new \Exception('Unauthorized to rate this exchange');
            }

            $ratedUserId = $data['rated_user_id'];

            if ($ratedUserId != $exchange->user_id && $ratedUserId != $exchange->to_user_id) {
                throw new \Exception('Invalid user for this exchange');
            }

            if ($raterUserId === $ratedUserId) {
                throw new \Exception('Cannot rate yourself');
            }

            $existingRating = UserRating::where('rated_user_id', $ratedUserId)
                ->where('rater_user_id', $raterUserId)
                ->where('exchange_id', $data['exchange_id'])
                ->where('status', 1)
                ->exists();

            if ($existingRating) {
                throw new \Exception('You have already rated this user in this exchange');
            }

            $rating = UserRating::create([
                'rated_user_id' => $ratedUserId,
                'rater_user_id' => $raterUserId,
                'rating' => $data['rating'],
                'exchange_id' => $data['exchange_id'],
                'created' => now(),
                'author' => $raterUserId,
                'status' => 1
            ]);

            $chatKey = $this->firebaseService->getChatKey($raterUserId, $ratedUserId, $exchange->exchange_id);

            $this->firebaseService->updateChatRoomRatingStatus($raterUserId, $ratedUserId, $chatKey);

            $otherUserHasRated = UserRating::where('rater_user_id', $ratedUserId)
                ->where('rated_user_id', $raterUserId)
                ->where('exchange_id', $exchange->exchange_id)
                ->exists();

            if ($otherUserHasRated) {
                $this->firebaseService->removeChatRoom($raterUserId, $ratedUserId, $chatKey);
            }

            return $rating;
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
