<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRating extends Model
{
    protected $table = 'trs_product_rating';
    protected $primaryKey = 'product_rating_id';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'created',
        'author',
        'status'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'users_id');
    }
}