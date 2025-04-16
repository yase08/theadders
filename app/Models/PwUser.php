<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PwUser extends Authenticatable
{
  use HasFactory, Notifiable;
  public const CREATED_AT = 'created';
  public const UPDATED_AT = 'updated';
  protected $table = 'pw_users';
  protected $primaryKey = 'id';

  protected $fillable = [
    'username',
    'nama_lengkap',
    'password',
    'tipe',
    'akses',
    'gambar',
    'last_login',
    'kodeacak',
    'created',
    'updated',
    'updater',
    'status',
  ];

  protected $hidden = [
    'password',
    'kodeacak',
  ];

  protected $casts = [
    'password' => 'hashed',
  ];
}
