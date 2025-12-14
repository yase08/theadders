<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboxOnboarding extends Model
{
    use HasFactory;

    protected $table = 'inbox_onboarding';

    protected $fillable = [
        'title',
        'description',
        'image',
        'button_text',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
