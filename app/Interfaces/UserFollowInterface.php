<?php

namespace App\Interfaces;

interface UserFollowInterface
{
    public function followUser($userId);
    public function unfollowUser($userId);
    public function getFollowers($userId);
    public function getFollowing($userId);
    public function checkFollowStatus($userId);
}