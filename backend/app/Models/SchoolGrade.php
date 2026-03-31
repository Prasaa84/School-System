<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolGrade extends Model
{
    protected $table = 'school_grade_tbl';
    protected $primaryKey = 'sch_grd_id';
    public $timestamps = false;

    protected $guarded = [];
}
