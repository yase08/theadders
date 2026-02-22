<?php

namespace App\Repositories;

use App\Interfaces\ProductCategoryInterface;
use App\Models\Categories;
use App\Models\Product;
use App\Models\CategorySub;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\ProductView;
use App\Models\Exchange;

class ProductCategoryRepository implements ProductCategoryInterface
{
    public function storeProduct($productData)
    {
        try {
            $product = Product::create([
                'category_id' => $productData['category_id'],

                'product_name' => $productData['product_name'],
                'description' => $productData['description'] ?? null,
                'thumbail' => $productData['thumbail'] ?? null,
                'price' => $productData['price'],
                'item_codition' => $productData['item_codition'] ?? null,
                'view_count' => 0,
                'author' => auth()->id(),
            ]);


            if (isset($productData['product_images']) && is_array($productData['product_images'])) {
                foreach ($productData['product_images'] as $image) {
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'file_image' => $image,
                        'created' => now(),
                        'author' => auth()->id(),
                        'status' => 1
                    ]);
                }
            }

            return $product->load('productImages');
        } catch (\Exception $e) {
            throw new \Exception('Unable to store product: ' . $e->getMessage());
        }
    }


    public function storeCategory($category)
    {

        try {
            $category = Categories::create([
                'category_name' => $category['category_name'],
                'icon' => $category['icon'],
                'author' => 'system',
                'status' => $category['status'],
            ]);

            return $category;
        } catch (\Exception $e) {
            throw new \Exception('Unable to store category: ' . $e->getMessage());
        }
    }

    public function storeSubCategory($subCategory)
    {
        try {
            $subCategory = CategorySub::create([
                'category_id' => $subCategory['category_id'],
                'category_name' => $subCategory['category_name'],
                'icon' => $subCategory['icon'],
                'author' => 'system',
                'status' => $subCategory['status'],
            ]);

            return $subCategory;
        } catch (\Exception $e) {
            throw new \Exception('Unable to store sub category: ' . $e->getMessage());
        }
    }

    public function getProducts(array $filters)
    {
        $userId = null;
        try {
            if ($token = \Tymon\JWTAuth\Facades\JWTAuth::getToken()) {
                $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate($token);
                if ($user) {
                    $userId = $user->users_id;
                }
            }
        } catch (\Exception $e) {
            
        }

        $allCompletedProductIds = Exchange::where('status', 'Completed')
            ->selectRaw('DISTINCT product_id as id')
            ->union(
                Exchange::where('status', 'Completed')
                    ->selectRaw('DISTINCT to_product_id as id')
            )
            ->pluck('id')->toArray();

       
        if ($userId) {
            $reportedProductIds = \App\Models\ProductReport::where('reporter_id', $userId)
                ->pluck('product_id')->toArray();
            
            $allCompletedProductIds = array_unique(array_merge($allCompletedProductIds, $reportedProductIds));
        }

        $query = Product::select([
                'product_id', 'category_id', 'category_sub_id', 'product_name', 
                'description', 'thumbail', 'price', 'item_codition', 'author', 'created'
            ])
            ->with([
                'category:category_id,category_name,icon',
                'categorySub:category_sub_id,category_name',
                'user:users_id,fullname,avatar,bio,created,location'
            ])
            ->withCount([
                'ratings as total_ratings' => function ($q) {
                    $q->where('status', 1);
                },
                'productLoves as is_wishlist' => function ($q) use ($userId) {
                    if ($userId) {
                        $q->where('user_id_author', $userId)->where('status', 1);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            ])
            ->withAvg([
                'ratings as average_rating' => function ($q) {
                    $q->where('status', 1);
                }
            ], 'rating');

        if ($userId) {
            $query->where('author', '!=', $userId);
        }

        if (!empty($allCompletedProductIds)) {
            $query->whereNotIn('product_id', $allCompletedProductIds);
        }

        $query->filter([
                'category_id' => $filters['category_id'] ?? null,
                'category_sub_id' => $filters['category_sub_id'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? null,
                'size' => $filters['size'] ?? null,
                'price_range' => $filters['price_range'] ?? null,
            ]);

        $result = isset($filters['per_page']) 
            ? $query->paginate($filters['per_page']) 
            : $query->get();

        $items = isset($filters['per_page']) ? $result->items() : $result;
        $grouped = [];
        
        foreach ($items as $product) {
            $categoryName = $product->category->category_name ?? 'Others';
            $grouped[$categoryName][] = $product;
        }

        return $grouped;
    }

    public function getUserProducts(array $filters)
    {
        $query = Product::with(['category', 'categorySub'])
            ->withCount(['ratings' => function ($q) {
                $q->where('status', 1);
            }])
            ->withAvg(['ratings' => function ($q) {
                $q->where('status', 1);
            }], 'rating')
            ->where('author', auth()->id())
            ->whereDoesntHave('exchangesAsRequester', function ($q) {
                $q->where('status', 'Completed');
            })
            ->whereDoesntHave('exchangesAsReceiver', function ($q) {
                $q->where('status', 'Completed');
            });

        $result = isset($filters['per_page'])
            ? $query->paginate($filters['per_page'])
            : $query->get();

        $products = isset($filters['per_page']) ? $result->getCollection() : $result;
        $products->each(function ($product) {
            $product->is_in_exchange = $this->isProductInActiveExchange($product->product_id);
        });

        return $result;
    }

    public function getUserTradeHistory(array $filters)
    {
        try {
            $userId = auth()->id();


            $query = Exchange::where('status', 'Completed')
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhere('to_user_id', $userId);
                })
                ->with([
                    'requesterProduct' => function ($q) {
                        $q->withCount('ratings')->withAvg('ratings', 'rating');
                    },
                    'receiverProduct' => function ($q) {
                        $q->withCount('ratings')->withAvg('ratings', 'rating');
                    },
                    'requester:users_id,fullname,avatar',
                    'receiver:users_id,fullname,avatar'
                ]);

            $query->orderBy('completed_at', 'desc');

            $result = isset($filters['per_page'])
                ? $query->paginate($filters['per_page'])
                : $query->get();

            return $result;
        } catch (\Exception $e) {
            throw new \Exception('Unable to get trade history: ' . $e->getMessage());
        }
    }

    public function getCategories(array $filters)
    {
        $query = Categories::query();

        if (!empty($filters['search'])) {
            $query->where('category_name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }

    public function getSubCategories(array $filters)
    {
        $query = CategorySub::query();

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('category_name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }

    public function getProductDetail($productId)
    {
        try {
            $product = Product::with(['category', 'categorySub', 'user', 'productImages', 'ratings'])
                ->where('product_id', $productId)
                ->firstOrFail();

            $ratings = $product->ratings()->where('status', 1)->get();
            $product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
            $product->total_ratings = $ratings->count();

            $product->is_in_exchange = $this->isProductInActiveExchange($productId);

            $product->increment('view_count');

            $this->trackProductView($product->product_id);

            return $product;
        } catch (\Exception $e) {
            throw new \Exception('Unable to fetch product details: ' . $e->getMessage());
        }
    }

    private function trackProductView($productId)
    {
        try {
            $request = request();

            $userId = 'guest';
            try {
                if ($token = \Tymon\JWTAuth\Facades\JWTAuth::getToken()) {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate($token);
                    if ($user) {
                        $userId = $user->users_id;
                    }
                }
            } catch (\Exception $e) {
                
            }

            ProductView::create([
                'product_id' => $productId,
                'useragent' => $request->userAgent(),
                'page' => $request->path(),
                'remote_addr' => $request->ip(),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'created' => now(),
                'author' => $userId,
                'status' => 1
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to track product view: ' . $e->getMessage());
        }
    }

    public function deleteProduct($productId)
    {
        try {
            $product = Product::where('product_id', $productId)
                ->where('author', auth()->id())
                ->firstOrFail();

            if ($this->isProductInActiveExchange($productId)) {
                throw new \Exception('Cannot delete product that is currently in an exchange process.');
            }

            ProductImage::where('product_id', $productId)->delete();

            $product->delete();

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Unable to delete product: ' . $e->getMessage());
        }
    }

    public function updateProduct($productId, $productData)
    {
        try {
            $product = Product::where('product_id', $productId)
                ->where('author', auth()->id())
                ->firstOrFail();

            if ($this->isProductInActiveExchange($productId)) {
                throw new \Exception('Cannot edit product that is currently in an exchange process.');
            }

            $updateData = [
                'category_id' => $productData['category_id'] ?? $product->category_id,

                'product_name' => $productData['product_name'] ?? $product->product_name,
                'description' => $productData['description'] ?? $product->description,
                'price' => $productData['price'] ?? $product->price,
                'item_codition' => $productData['item_codition'] ?? $product->item_codition,
            ];


            if (isset($productData['thumbail'])) {
                $updateData['thumbail'] = $productData['thumbail'];
            }

            $product->update($updateData);


            if (isset($productData['product_images']) && is_array($productData['product_images'])) {

                ProductImage::where('product_id', $productId)->delete();


                foreach ($productData['product_images'] as $image) {
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'file_image' => $image,
                        'created' => now(),
                        'author' => auth()->id(),
                        'status' => 1
                    ]);
                }
            }

            return $product->load('productImages');
        } catch (\Exception $e) {
            throw new \Exception('Unable to update product: ' . $e->getMessage());
        }
    }

    private function isProductInActiveExchange($productId): bool
    {
        return Exchange::whereIn('status', ['Submission', 'Approve'])
            ->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)
                  ->orWhere('to_product_id', $productId);
            })
            ->exists();
    }
}
