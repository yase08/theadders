<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskCategory extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_categories';

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'sort_order',
        'is_active',
    ];

    public function articles()
    {
        return $this->hasMany(HelpdeskArticle::class, 'category_id');
    }
}
