<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignUpRequest;
use App\Http\Resources\UserResource;
use App\Http\Requests\UpdateProfileRequest;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

// AuthController

class AuthController extends Controller
{
    private UserRepositoryInterface $userRepositoryInterface;

    public function __construct(UserRepositoryInterface $userRepositoryInterface)
    {
        $this->userRepositoryInterface = $userRepositoryInterface;
    }

    public function signUp(SignUpRequest $req)
    {
        DB::beginTransaction();
        try {
            if ($this->userRepositoryInterface->getUserByEmail($req->email)) {
                return ApiResponseClass::sendResponse(null, "Email already exists", 400);
            }

            $payloadUser = [
                'fullname' => $req->fullname,
                'email' => $req->email,
                'phone' => $req->phone,
                'status' => '1',
            ];

            $payloadPwUser = [
                'username' => $req->fullname,
                'nama_lengkap' => $req->fullname,
                'password' => bcrypt($req->password),
                'tipe' => 'system',
                'akses' => 'system',
                'kodeacak' => 'system',
                'updater' => 'system',
                'status' => '1',
            ];

            \Log::info('Saving password hash: ' . $payloadPwUser['password']);

            $user = $this->userRepositoryInterface->signUp($payloadUser, $payloadPwUser);

            DB::commit();
            return ApiResponseClass::sendResponse(new UserResource($user), "success", 201);
        } catch (\Exception  $ex) {
            DB::rollBack();
            return ApiResponseClass::sendResponse(null, "An error occurred: " . $ex->getMessage(), 500);
        }
    }

    public function login(LoginRequest $req)
    {
        $credentials = [
            'email' => $req->email,
            'password' => $req->password,
        ];

        try {
            \Log::info('Login attempt for email: ' . $credentials['email']); // Debugging log

            $user = $this->userRepositoryInterface->getUserByEmail($credentials['email']);

            $checked = Hash::check($req->password, $user->password);
            \Log::info('Input Password: ' . $req->password);
            \Log::info('Stored Password Hash: ' . $user->password);
            \Log::info('Password check result: ' . $checked);

            if (!$user || !Hash::check($req->password, $user->password)) {
                \Log::warning('Invalid credentials for email: ' . $credentials['email']); // Log jika salah
                return ApiResponseClass::sendResponse(null, "Invalid email or password", 401);
            }

            $token = auth()->guard('api')->login($user);

            \Log::info('Login successful for email: ' . $credentials['email']); // Log sukses

            \Log::info('Token: ' . $token);

            return response()->json([
                'user' => new UserResource($user),
                'token' => $token,
                "message" => "success"
            ], 200);
        } catch (\Throwable $ex) { // Gunakan \Throwable agar menangkap semua error
            \Log::error('Login error: ' . $ex->getMessage()); // Log error
            return response()->json(['error' => 'An error occurred: ' . $ex->getMessage()], 500);
        }
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $userData = $request->validated();

            if ($request->hasFile('avatar')) {
                $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
            }

            $user = $this->userRepositoryInterface->updateProfile(auth()->id(), $userData);

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                "success",
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(
                null,
                "An error occurred: " . $e->getMessage(),
                500
            );
        }
    }

    public function getProfile()
    {
        try {
            $user = auth()->user();
            return ApiResponseClass::sendResponse(
                new UserResource($user),
                "success",
                200
            );
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(
                null,
                "An error occurred: " . $e->getMessage(),
                500
            );
        }
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        auth()->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json(['message' => 'FCM token updated successfully']);
    }

    public function refreshToken()
    {
        try {
            $oldToken = auth()->guard('api')->getToken();
            $token = auth()->guard('api')->refresh();

            return response()->json([
                'token' => $token,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(
                null,
                "Token refresh failed: " . $e->getMessage(),
                401
            );
        }
    }
}
