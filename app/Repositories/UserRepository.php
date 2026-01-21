<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\PwUser;
use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function signUp(array $dataUser, array $dataPwUser)
    {
        $user = User::create([
            'fullname' => $dataUser['fullname'],
            'email' => $dataUser['email'],
            'phone' => $dataUser['phone'],
            'status' => $dataUser['status'],
            'password' => $dataPwUser['password']  
        ]);
        
        return $user->fresh();
    }

    public function getUserByEmail(string $email)
    {
        return User::where('email', $email)->first();  
    }

    public function updateProfile(int $userId, array $data)
    {
        $user = User::findOrFail($userId);

        if (isset($data['avatar']) && $user->avatar) {
            $oldAvatar = public_path($user->avatar);
            if (file_exists($oldAvatar)) {
                unlink($oldAvatar);
            }
        }

        $user->update($data);
        return $user->fresh();
    }

    public function getUserById($userId)
    {
        try {
            return User::withCount(['followers', 'wishlistItems', 'products'])
                ->find($userId);
        } catch (\Exception $e) {
            throw new \Exception('Error getting user by ID: ' . $e->getMessage());
        }
    }
}
