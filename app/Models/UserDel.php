<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDel extends Model
{
    protected $table = 'v2_user_del';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
}
