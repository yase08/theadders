<?php

namespace App\Interfaces;

interface ProductCategoryInterface
{
    public function storeProduct(array $data);
    public function getProducts(array $filters);
    public function getUserProducts(array $filters);
    public function getCategories(array $filters);
    public function getSubCategories(array $filters);
    public function storeCategory(array $data);
    public function storeSubCategory(array $data);
    public function getProductDetail($productId);
    public function getUserTradeHistory(array $filters);
    public function deleteProduct($productId);
    public function updateProduct($productId, array $data);
}
