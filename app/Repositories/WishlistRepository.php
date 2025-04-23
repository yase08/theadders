<?php

namespace App\Repositories;

use App\Interfaces\WishlistInterface;
use App\Models\ProductLove;
use App\Models\Product;

class WishlistRepository implements WishlistInterface
{
    public function addToWishlist(array $data)
    {
        try {
            // Check if product is already in wishlist
            $exists = ProductLove::where('user_id_author', auth()->id())
                ->where('product_id', $data['product_id'])
                ->where('status', 1)
                ->exists();

            if ($exists) {
                throw new \Exception('Product is already in wishlist');
            }

            // Get product author
            $product = Product::findOrFail($data['product_id']);

            return ProductLove::create([
                'product_id' => $data['product_id'],
                'user_id_author' => auth()->id(),
                'user_id' => $product->author,
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
            return ProductLove::where('user_id_author', auth()->id())
                ->where('product_id', $productId)
                ->delete();
        } catch (\Exception $e) {
            throw new \Exception('Unable to remove from wishlist: ' . $e->getMessage());
        }
    }

    public function getUserWishlist()
    {
        try {
            $wishlist = ProductLove::where('user_id_author', auth()->id())
                ->where('status', 1)
                ->with(['product.category', 'product.categorySub', 'product.ratings'])
                ->get();

            // Calculate ratings for each product in wishlist
            $wishlist->each(function ($item) {
                $ratings = $item->product->ratings()->where('status', 1)->get();
                $item->product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
                $item->product->total_ratings = $ratings->count();
            });

            return $wishlist;
        } catch (\Exception $e) {
            throw new \Exception('Unable to get wishlist: ' . $e->getMessage());
        }
    }

    public function checkWishlist(int $productId)
    {
        try {
            return ProductLove::where('user_id_author', auth()->id())
                ->where('product_id', $productId)
                ->where('status', 1)
                ->exists();
        } catch (\Exception $e) {
            throw new \Exception('Unable to check wishlist: ' . $e->getMessage());
        }
    }
}