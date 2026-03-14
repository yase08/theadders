<?php

namespace App\Http\Controllers;

use App\Models\DeviceRegistration;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:255',
        ]);

        $exists = DeviceRegistration::where('device_id', $request->device_id)->exists();

        return response()->json([
            'registered' => $exists,
            'message' => 'success'
        ], 200);
    }

    public function register(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:255',
            'user_id' => 'nullable|integer',
            'platform' => 'nullable|string|in:android,ios',
        ]);

        $device = DeviceRegistration::updateOrCreate(
            ['device_id' => $request->device_id],
            [
                'user_id' => $request->user_id,
                'platform' => $request->platform,
            ]
        );

        return response()->json([
            'message' => 'success',
            'data' => $device
        ], 201);
    }
}
