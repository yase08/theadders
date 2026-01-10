<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\FirebaseService;
use App\Classes\ApiResponseClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  protected $firebaseService;

  public function __construct(FirebaseService $firebaseService)
  {
    $this->firebaseService = $firebaseService;
  }

  public function markAsRead(Notification $notification): JsonResponse
  {
    if ($notification->user_id !== auth()->id()) {
      return ApiResponseClass::sendResponse(null, 'Unauthorized', 403);
    }

    try {
      if (is_null($notification->read_at)) {
        $notification->update(['read_at' => now()]);
        $this->firebaseService->syncUnreadNotificationCount(auth()->id());
      }

      return ApiResponseClass::sendResponse($notification, 'Notification marked as read');
    } catch (\Exception $e) {
      \Log::error('Failed to mark notification as read: ' . $e->getMessage());
      return ApiResponseClass::sendResponse(null, 'Failed to mark notification as read', 500);
    }
  }
}

