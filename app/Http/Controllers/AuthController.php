<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignUpRequest;
use App\Http\Resources\UserResource;
use App\Http\Requests\UpdateProfileRequest;
use App\Interfaces\UserRepositoryInterface;
use App\Models\ProductLove;
use App\Models\Product;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth; // Impor Firebase Auth

// AuthController

class AuthController extends Controller
{
    private UserRepositoryInterface $userRepositoryInterface;
    private FirebaseAuth $firebaseAuth; // Tambahkan properti untuk Firebase Auth

    public function __construct(UserRepositoryInterface $userRepositoryInterface, FirebaseAuth $firebaseAuth)
    {
        $this->userRepositoryInterface = $userRepositoryInterface;
        $this->firebaseAuth = $firebaseAuth; // Simpan instance Firebase Auth
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
                'password' => bcrypt($req->password),
            ];

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
            \Log::info('Login attempt for email: ' . $credentials['email']);

            $user = $this->userRepositoryInterface->getUserByEmail($credentials['email']);

            if (!$user) {
                \Log::warning('User not found for email: ' . $credentials['email']);
                return ApiResponseClass::sendResponse(null, "Invalid email or password", 401);
            }

            if (!Hash::check($req->password, $user->password)) {
                \Log::warning('Invalid password for email: ' . $credentials['email']);
                return ApiResponseClass::sendResponse(null, "Invalid email or password", 401);
            }

            $laravelApiToken = auth()->guard('api')->login($user);

            \Log::info('Laravel login successful for email: ' . $credentials['email']);
            \Log::info('Laravel API Token: ' . $laravelApiToken);

            $firebaseCustomToken = null; // Inisialisasi sebagai null
            $firebaseUid = (string) $user->users_id; 

            try {
                // 1. Buat custom token Firebase (ini mengembalikan objek Lcobucci\JWT\Token\Plain)
                $firebaseTokenObject = $this->firebaseAuth->createCustomToken($firebaseUid);
                
                // 2. Konversi objek token ke string menggunakan method toString()
                $firebaseCustomToken = $firebaseTokenObject->toString(); 
                
                \Log::info('Firebase custom token created for UID: ' . $firebaseUid); // Log UID
                // \Log::info('Firebase custom token string: ' . $firebaseCustomToken); // Opsional: Log token stringnya jika perlu
            } catch (\Kreait\Firebase\Exception\AuthException $e) {
                \Log::error('Firebase custom token creation failed: ' . $e->getMessage());
                // $firebaseCustomToken sudah null, jadi tidak perlu diubah
            } catch (\Exception $e) { // Menangkap exception lain yang mungkin terjadi (misal saat toString())
                \Log::error('Error processing Firebase token: ' . $e->getMessage());
                // $firebaseCustomToken sudah null atau biarkan null
            }

            return response()->json([
                'user' => new UserResource($user),
                'token' => $laravelApiToken, 
                'firebase_custom_token' => $firebaseCustomToken, // Ini akan berupa string token atau null jika gagal
                "message" => "success"
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Login error: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine());
            \Log::error($ex->getTraceAsString()); // Tambahkan stack trace untuk detail lebih
            return response()->json(['error' => 'An error occurred during login.'], 500);
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
            $userStats = $this->getUserStats($user->users_id);

            $response = (new UserResource($user))->additional([
                'stats' => [
                    'followers_count' => $userStats['followers_count'],
                    'wishlist_count' => $userStats['wishlist_count'],
                    'products_count' => $userStats['products_count']
                ]
            ]);

            return ApiResponseClass::sendResponse(
                $response,
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

    public function getUserById($userId)
    {
        try {
            $user = $this->userRepositoryInterface->getUserById($userId);
            
            if (!$user) {
                return ApiResponseClass::sendResponse(null, "User not found", 404);
            }

            $userStats = $this->getUserStats($user->users_id);

            $response = (new UserResource($user))->additional([
                'stats' => [
                    'followers_count' => $userStats['followers_count'],
                    'wishlist_count' => $userStats['wishlist_count'],
                    'products_count' => $userStats['products_count']
                ]
            ]);

            return ApiResponseClass::sendResponse($response, "success", 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, "An error occurred: " . $e->getMessage(), 500);
        }
    }
    private function getUserStats($userId)
    {
        return [
            'followers_count' => UserFollow::where('users_id', $userId)->count(),
            'wishlist_count' => ProductLove::where('user_id_author', $userId)->count(),
            'products_count' => Product::where('author', $userId)->count()
        ];
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
