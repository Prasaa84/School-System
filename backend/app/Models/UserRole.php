<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'user_role_tbl';
    protected $primaryKey = 'role_id';
    public $timestamps = false;

    protected $guarded = [];
}
