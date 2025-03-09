<?php

namespace App\Repositories;

use App\Interfaces\ProductCategoryInterface;
use App\Models\Categories;
use App\Models\Product;
use App\Models\CategorySub;
use App\Models\User;

class ProductCategoryRepository implements ProductCategoryInterface
{
    public function storeProduct($productData)
    {
        try {
            return Product::create([
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
        $query = User::with(['products.category', 'products.categorySub'])
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

        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }


    public function getUserProducts(array $filters)
    {
        $query = Product::with(['category', 'categorySub'])
            ->where('author', auth()->id());

        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
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
}
