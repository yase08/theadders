<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReport extends Model
{
    protected $table = 'product_reports';

    protected $fillable = [
        'product_id',
        'reporter_id',
        'reason',
        'description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id', 'users_id');
    }
}
