<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'student_tbl';
    protected $primaryKey = 'std_id';
    public $timestamps = false;

    protected $guarded = [];
}

