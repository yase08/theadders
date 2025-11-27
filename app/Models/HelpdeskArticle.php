<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskArticle extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_articles';

    protected $fillable = [
        'category_id',
        'title',
        'content',
        'type',
        'video_url',
        'link_url',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }
}
