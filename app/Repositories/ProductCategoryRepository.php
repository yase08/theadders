<?php

namespace App\Repositories;

use App\Interfaces\ProductCategoryInterface;
use App\Models\Categories;
use App\Models\Product;
use App\Models\CategorySub;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\ProductView;

class ProductCategoryRepository implements ProductCategoryInterface
{
    public function storeProduct($productData)
    {
        try {
            $product = Product::create([
                'category_id' => $productData['category_id'],
                'category_sub_id' => $productData['category_sub_id'],
                'product_name' => $productData['product_name'],
                'description' => $productData['description'] ?? null,
                'thumbail' => $productData['thumbail'] ?? null,
                'price' => $productData['price'],
                'end_price' => $productData['end_price'] ?? null,
                'year_release' => $productData['year_release'] ?? null,
                'buy_release' => $productData['buy_release'] ?? null,
                'item_codition' => $productData['item_codition'] ?? null,
                'view_count' => $productData['view_count'] ?? 0,
                'author' => auth()->id(),
                'status' => $productData['status'],
            ]);

            // Store product images if provided
            if (isset($productData['product_images']) && is_array($productData['product_images'])) {
                foreach ($productData['product_images'] as $image) {
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'file_image' => $image,
                        'created' => now(),
                        'author' => auth()->id(),
                        'status' => 1 // Active status
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
        // Implementasi untuk menyimpan kategori
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
        $query = User::with(['products.category', 'products.categorySub', 'products.ratings'])
            ->whereHas('products', function ($query) use ($filters) {
                $query->filter([
                    'category_id' => $filters['category_id'] ?? null,
                    'category_sub_id' => $filters['category_sub_id'] ?? null,
                    'search' => $filters['search'] ?? null,
                    'sort' => $filters['sort'] ?? null,
                    'size' => $filters['size'] ?? null,
                    'price_range' => $filters['price_range'] ?? null,
                ]);
            })
            ->where('users_id', '!=', auth()->id());

        $result = isset($filters['per_page']) ? $query->paginate($filters['per_page']) : $query->get();

        // Calculate ratings for each product
        $result->each(function ($user) {
            $user->products->each(function ($product) {
                $ratings = $product->ratings()->where('status', 1)->get();
                $product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
                $product->total_ratings = $ratings->count();
            });
        });

        return $result;
    }


    public function getUserProducts(array $filters)
    {
        $query = Product::with(['category', 'categorySub', 'ratings'])
            ->where('author', auth()->id());

        $result = isset($filters['per_page']) 
            ? $query->paginate($filters['per_page']) 
            : $query->get();

        // Calculate ratings for each product
        $result->each(function ($product) {
            $ratings = $product->ratings()->where('status', 1)->get();
            $product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
            $product->total_ratings = $ratings->count();
        });

        return $result;
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

            // Calculate ratings
            $ratings = $product->ratings()->where('status', 1)->get();
            $product->average_rating = round($ratings->avg('rating'), 1) ?? 0;
            $product->total_ratings = $ratings->count();

            // Increment view count
            $product->increment('view_count');

            // Track product view
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
            
            ProductView::create([
                'product_id' => $productId,
                'useragent' => $request->userAgent(),
                'page' => $request->path(),
                'remote_addr' => $request->ip(),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'created' => now(),
                'author' => auth()->check() ? auth()->id() : 'guest',
                'status' => 1
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to track product view: ' . $e->getMessage());
        }
    }
}
