<?php

namespace App\Interfaces;

interface UserRepositoryInterface
{
    public function signUp(array $dataUser, array $dataPwUser);
    public function getUserByEmail(string $email);
    public function updateProfile(int $userId, array $data);
    public function getUserById(int $userId);
}
