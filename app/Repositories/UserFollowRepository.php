<?php

namespace App\Repositories;

use App\Interfaces\UserFollowInterface;
use App\Models\UserFollow;

class UserFollowRepository implements UserFollowInterface
{
    public function followUser($userId)
    {
        try {
            return UserFollow::create([
                'users_id' => $userId,
                'users_follower' => auth()->id(),
                'created' => now(),
                'status' => 1
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Unable to follow user: ' . $e->getMessage());
        }
    }

    public function unfollowUser($userId)
    {
        try {
            return UserFollow::where('users_id', $userId)
                ->where('users_follower', auth()->id())
                ->delete();
        } catch (\Exception $e) {
            throw new \Exception('Unable to unfollow user: ' . $e->getMessage());
        }
    }

    public function getFollowers($userId)
    {
        try {
            return UserFollow::where('users_id', $userId)
                ->with('follower')
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Unable to get followers: ' . $e->getMessage());
        }
    }

    public function getFollowing($userId)
    {
        try {
            return UserFollow::where('users_follower', $userId)
                ->with('user')
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Unable to get following: ' . $e->getMessage());
        }
    }

    public function checkFollowStatus($userId)
    {
        try {
            return UserFollow::where('users_id', $userId)
                ->where('users_follower', auth()->id())
                ->exists();
        } catch (\Exception $e) {
            throw new \Exception('Unable to check follow status: ' . $e->getMessage());
        }
    }
}