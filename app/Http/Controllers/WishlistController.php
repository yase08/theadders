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

  public function getUserWishlist()
  {
    try {
      $wishlist = $this->wishlistInterface->getUserWishlist();
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
}
