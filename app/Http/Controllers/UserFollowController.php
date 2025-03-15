<?php

namespace App\Http\Controllers;

use App\Interfaces\UserFollowInterface;
use Illuminate\Http\Request;
use App\Classes\ApiResponseClass;

class UserFollowController extends Controller
{
  private UserFollowInterface $userFollowInterface;

  public function __construct(UserFollowInterface $userFollowInterface)
  {
    $this->userFollowInterface = $userFollowInterface;
  }

  public function follow($userId)
  {
    try {
      $follow = $this->userFollowInterface->followUser($userId);
      return response()->json([
        'message' => 'success',
        'follow' => $follow
      ], 201);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function unfollow($userId)
  {
    try {
      $this->userFollowInterface->unfollowUser($userId);
      return response()->json([
        'message' => 'success',
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function followers($userId)
  {
    try {
      $followers = $this->userFollowInterface->getFollowers($userId);
      return response()->json([
        'message' => 'success',
        'followers' => $followers
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function following($userId)
  {
    try {
      $following = $this->userFollowInterface->getFollowing($userId);
      return response()->json([
        'message' => 'success',
        'following' => $following
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }

  public function checkFollow($userId)
  {
    try {
      $isFollowing = $this->userFollowInterface->checkFollowStatus($userId);
      return response()->json([
        'message' => 'success',
        'isFollowing' => $isFollowing
      ], 200);
    } catch (\Throwable $th) {
      return response()->json([
        'message' => 'error',
        'error' => $th->getMessage()
      ], 500);
    }
  }
}
