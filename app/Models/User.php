<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';
    protected $table = 'users';
    protected $primaryKey = 'users_id';

    protected $fillable = [
        'fullname',
        'email',
        'phone',
        'author',
        'created',
        'updated',
        'updater',
        'avatar',
        'location',
        'bio',
        'fcm_token',
        'password',
        'firebase_uid',
        'hobbies', 
        'toys',
        'fashion'
    ];

    protected $hidden = [
        'remember_token',
        'password',  // Hide password from JSON/array output
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'author', 'users_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function followers()
    {
        return $this->hasMany(UserFollow::class, 'users_id', 'users_id');
    }

    public function wishlistItems()
    {
        return $this->hasMany(ProductLove::class, 'user_id', 'users_id');
    }


    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
