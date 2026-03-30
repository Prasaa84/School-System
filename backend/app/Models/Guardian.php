<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $table = 'guardian_tbl';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];
}
