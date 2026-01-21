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
                ->whereHas('product') 
                ->with([
                    'product' => function ($query) {
                        $query->with(['category', 'categorySub'])
                            ->withCount(['ratings' => function ($q) {
                                $q->where('status', 1);
                            }])
                            ->withAvg(['ratings' => function ($q) {
                                $q->where('status', 1);
                            }], 'rating');
                    }
                ])
                ->when($search, function ($query, $search) {
                    $query->whereHas('product', function ($q) use ($search) {
                        $q->where('product_name', 'like', '%' . $search . '%');
                    });
                })
                ->get();

            // Map to add computed fields
            $wishlist->each(function ($item) {
                if ($item->product) {
                    $item->product->average_rating = round($item->product->ratings_avg_rating ?? 0, 1);
                    $item->product->total_ratings = $item->product->ratings_count ?? 0;
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
            
            $productLoves = ProductLove::where('user_id', $userId)
                ->with([
                    'authorUser' => function ($query) {
                        $query->select('users_id', 'fullname', 'email', 'avatar');
                    },
                    'product' => function ($query) {
                        $query->select('product_id', 'product_name', 'thumbail', 'price', 'author');
                    }
                ])
                ->get();

            $wishlistersWithProducts = collect();

            
            $productLoves->groupBy('user_id_author')->each(function ($wishlistItems, $wishlisterId) use ($wishlistersWithProducts) {
                $firstItem = $wishlistItems->first();
                if ($firstItem && $firstItem->authorUser) {
                    $wishlisterData = $firstItem->authorUser->toArray(); 
                    
                    $wishlisterData['wishlisted_products'] = $wishlistItems->map(function ($item) {
                        return $item->product; 
                    })->filter()->values(); 

                    $wishlistersWithProducts->push($wishlisterData);
                }
            });

            return $wishlistersWithProducts;
        } catch (\Exception $e) {
            throw new \Exception('Unable to get users who wishlisted your products: ' . $e->getMessage());
        }
    }
}