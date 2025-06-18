<?php

namespace App\Http\Controllers;

use App\Interfaces\RatingInterface;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;

class RatingController extends Controller
{
    private RatingInterface $ratingInterface;

    public function __construct(RatingInterface $ratingInterface)
    {
        $this->ratingInterface = $ratingInterface;
    }

    public function rateExchangeProduct(Request $request)
    {
        $request->validate([
            'exchange_id' => 'required|exists:trs_exchange,exchange_id',
            'rating' => 'required|integer|between:1,5'
        ]);

        try {
            $rating = $this->ratingInterface->rateExchangeProduct($request->all());
            return response()->json([
                'message' => 'success',
                'rating' => $rating
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getProductRatings($productId)
    {
        try {
            $ratings = $this->ratingInterface->getProductRatings($productId);
            return response()->json([
                'message' => 'success',
                'ratings' => $ratings
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function rateExchangeUser(Request $request)
    {
        $request->validate([
            'exchange_id' => 'required|exists:trs_exchange,exchange_id',
            'rated_user_id' => 'required|exists:users,users_id',
            'rating' => 'required|integer|between:1,5'
        ]);

        try {
            $rating = $this->ratingInterface->rateExchangeUser($request->all());
            return response()->json([
                'message' => 'success',
                'rating' => $rating
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getUserRatings($userId)
    {
        try {
            $ratings = $this->ratingInterface->getUserRatings($userId);
            return response()->json([
                'message' => 'success',
                'ratings' => $ratings
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getGivenRatings($userId)
    {
        try {
            $ratings = $this->ratingInterface->getGivenRatings($userId);
            return response()->json([
                'message' => 'success',
                'ratings' => $ratings
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
