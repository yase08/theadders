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
use Kreait\Firebase\Auth as FirebaseAuth; 

class AuthController extends Controller
{
    private UserRepositoryInterface $userRepositoryInterface;
    private FirebaseAuth $firebaseAuth; 

    public function __construct(UserRepositoryInterface $userRepositoryInterface, FirebaseAuth $firebaseAuth)
    {
        $this->userRepositoryInterface = $userRepositoryInterface;
        $this->firebaseAuth = $firebaseAuth; 
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

            $user->firebase_uid = (string) $user->users_id;
            $user->save();

            DB::commit();

            return response()->json([
                'user' => new UserResource($user->refresh()),
                'firebase_uid' => $user->firebase_uid,
                "message" => "success"
            ], 201);

        } catch (\Exception $ex) {
            DB::rollBack();
           \Log::error('Signup error: ' . $ex->getMessage() . ' Stack: ' . $ex->getTraceAsString());
            return ApiResponseClass::sendResponse(null, "An error occurred during registration: " . $ex->getMessage(), 500);
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

            if (empty($user->firebase_uid)) {
                $user->firebase_uid = (string) $user->users_id;
                $user->save();
                $user->refresh(); 
               \Log::info('Populated empty firebase_uid for user: ' . $user->email . ' with Laravel ID: ' . $user->firebase_uid);
            }

            $laravelApiToken = auth()->guard('api')->login($user);
           \Log::info('Laravel login successful for email: ' . $user->email);
           \Log::info('Laravel API Token: ' . $laravelApiToken);

            $firebaseCustomTokenString = null;
            $firebaseUidForToken = $user->firebase_uid;

            try {
                $firebaseTokenObject = $this->firebaseAuth->createCustomToken($firebaseUidForToken);
                $firebaseCustomTokenString = $firebaseTokenObject->toString();
               \Log::info('Firebase custom token created for UID: ' . $firebaseUidForToken);
            } catch (\Exception $e) {
               \Log::error('Error processing Firebase custom token during login: ' . $e->getMessage());
            }

            return response()->json([
                'user' => new UserResource($user),
                'token' => $laravelApiToken,
                'firebase_custom_token' => $firebaseCustomTokenString,
                'firebase_uid' => $user->firebase_uid,
                "message" => "success"
            ], 200);

        } catch (\Throwable $ex) {
           \Log::error('Login error: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine());
           \Log::error($ex->getTraceAsString());
            return response()->json(['error' => 'An error occurred during login.'], 500);
        }
    }

    public function syncFirebaseUser(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'email' => 'required|string|email|max:255',
                'firebase_uid' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
           \Log::error('Validation failed for syncFirebaseUser: ', $e->errors());
            return ApiResponseClass::sendResponse($e->errors(), 'Validation errors', 422);
        }

        DB::beginTransaction();
        try {
            $email = $validatedData['email'];
            $actualFirebaseUid = $validatedData['firebase_uid'];

            $user = User::where('email', $email)->first();

            if (!$user) {
                DB::rollBack();
               \Log::warning("User not found with email: {$email} during syncFirebaseUser. Client states user should already be registered.");
                return ApiResponseClass::sendResponse(null, 'User with this email not found. Please ensure the user is registered in Laravel first.', 404);
            }

            $conflictingUser = User::where('firebase_uid', $actualFirebaseUid)
                                   ->where('users_id', '!=', $user->users_id)
                                   ->first();

            if ($conflictingUser) {
                DB::rollBack();
               \Log::error("Conflict: Firebase UID {$actualFirebaseUid} is already linked to a different Laravel user (ID: {$conflictingUser->users_id}). Cannot link to user {$user->users_id} with email {$email}.");
                return ApiResponseClass::sendResponse(null, 'This Firebase account (Google/Apple) is already linked to another user profile in our system.', 409);
            }

            if ($user->firebase_uid !== $actualFirebaseUid) {
                $user->firebase_uid = $actualFirebaseUid;
               \Log::info("Updating Firebase UID to '{$actualFirebaseUid}' for user with email: {$email} (Laravel User ID: {$user->users_id})");
            } else {
               \Log::info("Firebase UID '{$actualFirebaseUid}' already set for user with email: {$email} (Laravel User ID: {$user->users_id}). No update needed for firebase_uid itself.");
            }
            
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();


            $laravelApiToken = auth()->guard('api')->login($user);
            DB::commit();

            return response()->json([
                'user' => new UserResource($user->refresh()),
                'firebase_uid' => $user->firebase_uid,
                'token' => $laravelApiToken,
                "message" => "success"
            ], 200);

        } catch (\Throwable $ex) {
            DB::rollBack();
           \Log::error('Error in syncFirebaseUser: ' . $ex->getMessage() . ' Stack: ' . $ex->getTraceAsString());
            return ApiResponseClass::sendResponse(null, 'An error occurred during user sync: ' . $ex->getMessage(), 500);
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

    public function logout(Request $request)
    {
        try {
            auth()->guard('api')->logout();

            return response()->json([
                'message' => 'success',
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Logout error: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine());
            return ApiResponseClass::sendResponse(null, 'An error occurred during logout.', 500);
        }
    }
}
