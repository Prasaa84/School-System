<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolGradeClass extends Model
{
    protected $table = 'school_grade_class_tbl';
    protected $primaryKey = 'sch_grd_cls_id';
    public $timestamps = false;

    protected $guarded = [];

    public function studentAssignments(): HasMany
    {
        return $this->hasMany(StudentGradeClass::class, 'sch_grd_cls_id', 'sch_grd_cls_id');
    }
}
