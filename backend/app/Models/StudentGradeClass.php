<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGradeClass extends Model
{
    protected $table = 'student_grade_class_tbl';
    protected $primaryKey = 'st_gr_cl_id';
    public $timestamps = false;

    protected $guarded = [];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'std_id', 'std_id');
    }

    public function schoolGradeClass(): BelongsTo
    {
        return $this->belongsTo(SchoolGradeClass::class, 'sch_grd_cls_id', 'sch_grd_cls_id');
    }
}
