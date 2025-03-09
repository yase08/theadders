<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorySub extends Model
{
    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';
    protected $table = 'mst_category_sub';
    protected $primaryKey = 'category_sub_id';

    protected $fillable = [
        'category_name',
        'category_id',
        'icon',
        'author',
        'created',
        'updated',
        'updater',
        'status',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_sub_id');
    }

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }
}
