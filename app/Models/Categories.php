<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';
    protected $table = 'mst_category';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'icon',
        'author',
        'created',
        'updated',
        'updater',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
