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
            
            $exists = ProductLove::where('user_id_author', auth()->id())
                ->where('product_id', $data['product_id'])
                ->where('status', 1)
                ->exists();

            if ($exists) {
                throw new \Exception('Product is already in wishlist');
            }

            
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

    public function getUserWishlist($search = null) 
    {
        try {
            $wishlist = ProductLove::where('user_id_author', auth()->id())
                ->where('status', 1)
                ->with(['product.category', 'product.categorySub', 'product.ratings'])
                
                ->when($search, function ($query, $search) {
                    $query->whereHas('product', function ($q) use ($search) {
                        $q->where('product_name', 'like', '%' . $search . '%');
                    });
                })
                ->get();

            
            $wishlist->each(function ($item) {
                
                if ($item->product) {
                    $ratings = $item->product->ratings()->where('status', 1)->get();
                    $item->product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
                    $item->product->total_ratings = $ratings->count();
                } else {
                    
                    $item->product->average_rating = 0;
                    $item->product->total_ratings = 0;
                }
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

    public function getUsersWhoWishlistedMyProducts(int $userId)
    {
        try {
            
            $wishlisters = ProductLove::where('user_id', $userId) 
                ->with(['authorUser' => function ($query) { 
                    $query->select('users_id', 'fullname', 'email', 'avatar'); 
                }])
                ->get()
                ->pluck('authorUser') 
                ->filter() 
                ->unique('users_id') 
                ->values(); 

            return $wishlisters;
        } catch (\Exception $e) {
            throw new \Exception('Unable to get users who wishlisted your products: ' . $e->getMessage());
        }
    }
}