<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFollow extends Model
{
    protected $table = 'users_follow';
    protected $primaryKey = 'users_follow_id';
    public $timestamps = false;

    protected $fillable = [
        'users_id',
        'users_follower',
        'created',
        'updated',
        'updated_by',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id', 'users_id');
    }

    public function follower()
    {
        return $this->belongsTo(User::class, 'users_follower', 'users_id');
    }
}