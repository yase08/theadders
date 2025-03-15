<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLove extends Model
{
    protected $table = 'trs_product_love';
    protected $primaryKey = 'product_love_id';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'user_id_author',
        'user_id',
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

    public function authorUser()
    {
        return $this->belongsTo(User::class, 'user_id_author', 'users_id');
    }
}