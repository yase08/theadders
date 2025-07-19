<?php

namespace App\Http\Controllers;

use App\Interfaces\WishlistInterface;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;
use App\Http\Requests\ProductLoveRequest;

class WishlistController extends Controller
{
  private WishlistInterface $wishlistInterface;

  public function __construct(WishlistInterface $wishlistInterface)
  {
    $this->wishlistInterface = $wishlistInterface;
  }

  public function addToWishlist(ProductLoveRequest $request)
  {
    try {
      $wishlist = $this->wishlistInterface->addToWishlist($request->validated());
      return response()->json([
        'message' => 'success',
        'wishlist' => $wishlist
      ], 201);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function removeFromWishlist($productId)
  {
    try {
      $this->wishlistInterface->removeFromWishlist($productId);
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

  public function getUserWishlist(Request $request)
  {
    try {
      $search = $request->query('search');
      $wishlist = $this->wishlistInterface->getUserWishlist($search);
      return response()->json([
        'message' => 'success',
        'wishlist' => $wishlist
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function checkWishlist($productId)
  {
    try {
      $exists = $this->wishlistInterface->checkWishlist($productId);
      return response()->json([
        'message' => 'success',
        'exists' => $exists
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function getUsersWhoWishlistedMyProducts()
  {
    try {
      $currentUserId = auth()->id();
      $wishlisters = $this->wishlistInterface->getUsersWhoWishlistedMyProducts($currentUserId);
      return response()->json([
        'message' => 'success',
        'wishlist' => $wishlisters
      ], 200);
    } catch (\Throwable $th) {
      \Log::error('Failed to get users who wishlisted products: ' . $th->getMessage() . ' Stack: ' . $th->getTraceAsString());
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }
}
