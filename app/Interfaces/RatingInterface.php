<?php

namespace App\Interfaces;

interface RatingInterface
{
    public function rateExchangeProduct(array $data);
    public function getProductRatings(int $productId);
    public function getUserRatings(int $userId);
}