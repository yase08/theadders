<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRating extends Model
{
    protected $table = 'trs_user_rating';
    protected $primaryKey = 'user_rating_id';
    public $timestamps = false;

    protected $fillable = [
        'rated_user_id',
        'rater_user_id',
        'rating',
        'exchange_id',
        'created',
        'author',
        'status'
    ];

    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id', 'users_id');
    }

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_user_id', 'users_id');
    }

    public function exchange()
    {
        return $this->belongsTo(Exchange::class, 'exchange_id', 'exchange_id');
    }
}