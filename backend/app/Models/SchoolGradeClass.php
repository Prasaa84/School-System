<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolGradeClass extends Model
{
    protected $table = 'school_grade_class_tbl';
    protected $primaryKey = 'sch_grd_cls_id';
    public $timestamps = false;

    protected $guarded = [];
}
