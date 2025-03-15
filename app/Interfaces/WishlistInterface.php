<?php

namespace App\Interfaces;

interface WishlistInterface
{
    public function addToWishlist(array $data);
    public function removeFromWishlist(int $productId);
    public function getUserWishlist();
    public function checkWishlist(int $productId);
}