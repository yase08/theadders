<?php

namespace App\Repositories;

use App\Interfaces\WishlistInterface;
use App\Models\ProductLove;

class WishlistRepository implements WishlistInterface
{
    public function addToWishlist(array $data)
    {
        try {
            return ProductLove::create([
                'product_id' => $data['product_id'],
                'user_id_author' => auth()->id(),
                'user_id' => $data['user_id'] ?? auth()->id(),
                'created' => now(),
                'author' => 'system',
                'status' => 1
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Unable to add to wishlist: ' . $e->getMessage());
        }
    }

    public function removeFromWishlist(int $productId)
    {
        try {
            return ProductLove::where('user_id', auth()->id())
                ->where('product_id', $productId)
                ->delete();
        } catch (\Exception $e) {
            throw new \Exception('Unable to remove from wishlist: ' . $e->getMessage());
        }
    }

    public function getUserWishlist()
    {
        try {
            return ProductLove::where('user_id', auth()->id())
                ->where('status', 1)
                ->with(['product.category', 'product.categorySub'])
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Unable to get wishlist: ' . $e->getMessage());
        }
    }

    public function checkWishlist(int $productId)
    {
        try {
            return ProductLove::where('user_id', auth()->id())
                ->where('product_id', $productId)
                ->where('status', 1)
                ->exists();
        } catch (\Exception $e) {
            throw new \Exception('Unable to check wishlist: ' . $e->getMessage());
        }
    }
}