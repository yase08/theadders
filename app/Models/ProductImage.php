<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'table_product_images';
    protected $primaryKey = 'product_image_id';
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'file_image',
        'created',
        'author',
        'updated',
        'updated_by',
        'status'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}