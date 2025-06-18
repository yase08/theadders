<?php

namespace App\Interfaces;

interface RatingInterface
{
    public function rateExchangeUser(array $data);
    public function getUserRatings(int $userId);
    public function getGivenRatings(int $userId);
}