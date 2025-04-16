<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\PwUser;
use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function signUp(array $dataUser, array $dataPwUser)
    {
        $user = User::create($dataUser);
        $user->refresh();

        $pwUser = new PwUser();
        $pwUser->id = $user->users_id;
        $pwUser->username = $dataPwUser['username'];
        $pwUser->nama_lengkap = $dataPwUser['nama_lengkap'];
        $pwUser->password = $dataPwUser['password'];
        $pwUser->tipe = $dataPwUser['tipe'];
        $pwUser->akses = $dataPwUser['akses'];
        $pwUser->kodeacak = $dataPwUser['kodeacak'];
        $pwUser->updater = $dataPwUser['updater'];
        $pwUser->status = $dataPwUser['status'];
        $pwUser->save();

        return $user;
    }

    public function getUserByEmail(string $email)
    {
        return User::where('email', $email)->join('pw_users', 'users.users_id', '=', 'pw_users.id')->select('users.*', 'pw_users.password')->first();
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
            $user = User::where('users_id', $userId)
                ->first();

            return $user;
        } catch (\Exception $e) {
            throw new \Exception('Error getting user by ID: ' . $e->getMessage());
        }
    }
}
