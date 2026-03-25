<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SdsUser extends Model
{
    protected $table = 'user_tbl';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role_id', 'role_id');
    }
}
