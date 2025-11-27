<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskNavigation extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_navigation';

    protected $fillable = [
        'title',
        'source',
        'action',
        'target',
        'sort_order',
        'is_active',
    ];
}
