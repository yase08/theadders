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
        $dataPwUser['id'] = $user->user_id;

        PwUser::create($dataPwUser);

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
}
