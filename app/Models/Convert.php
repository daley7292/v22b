<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convert extends Model
{
    protected $table = 'v2_convert';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
