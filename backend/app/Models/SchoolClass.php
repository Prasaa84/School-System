<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table = 'class_tbl';
    protected $primaryKey = 'class_id';
    public $timestamps = false;

    protected $guarded = [];
}
