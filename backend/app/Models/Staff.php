<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $table = 'staff_tbl';
    protected $primaryKey = 'stf_id';
    public $timestamps = false;

    protected $guarded = [];
}
