<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    protected $table = 'user_reports';

    protected $fillable = [
        'reported_user_id',
        'reporter_id',
        'reason',
        'title',
        'description',
    ];

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id', 'users_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id', 'users_id');
    }
}
