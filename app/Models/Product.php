<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductLove;

class Product extends Model
{
    protected $table = 'tabel_product';
    protected $primaryKey = 'product_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'category_id',
        'category_sub_id',
        'product_name',
        'description',
        'thumbail',
        'price',
        'item_codition',
        'view_count',
        'created',
        'author',
        'updated',
        'updater',
    ];

    protected $casts = [
        // 'price' => 'decimal:2',
        'view_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function categorySub()
    {
        return $this->belongsTo(CategorySub::class, 'category_sub_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'author');
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'product_id');
    }

    public function ratings()
    {
        return $this->hasMany(ProductRating::class, 'product_id', 'product_id');
    }

    public function productLoves()
    {
        return $this->hasMany(ProductLove::class, 'product_id', 'product_id');
    }

    public function exchangesAsRequester()
    {
        return $this->hasMany(Exchange::class, 'product_id', 'product_id');
    }

    public function exchangesAsReceiver()
    {
        return $this->hasMany(Exchange::class, 'to_product_id', 'product_id');
    }


    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['category_id'] ?? false, function ($query, $categoryId) {
            return $query->where('category_id', $categoryId);
        });

        $query->when($filters['category_sub_id'] ?? false, function ($query, $subCategoryId) {
            return $query->where('category_sub_id', $subCategoryId);
        });

        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where(function ($query) use ($search) {
                $query->where('product_name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        });

        $query->when($filters['size'] ?? false, function ($query, $size) {
            return $query->where('size', $size);
        });

        $query->when($filters['price_range'] ?? false, function ($query, $range) {
            return $query->where('price', $range);
        });

        $query->when($filters['sort'] ?? false, function ($query, $sort) {
            return match ($sort) {
                'recent' => $query->orderBy('created', 'desc'),
                'relevance' => $query->orderBy('view_count', 'desc'),
                default => $query->orderBy('created', 'desc'),
            };
        });

        if (!empty($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query;
    }
}
