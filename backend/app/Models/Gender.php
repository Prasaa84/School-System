<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    protected $table = 'gender_tbl';
    protected $primaryKey = 'gender_id';
    public $timestamps = false;

    protected $guarded = [];
}
