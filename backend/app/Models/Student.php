<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $table = 'student_tbl';
    protected $primaryKey = 'std_id';
    public $timestamps = false;

    protected $guarded = [];

    public function school(): BelongsTo
    {
        return $this->belongsTo(SchoolDetail::class, 'census_id', 'census_id');
    }

    public function gradeClassAssignments(): HasMany
    {
        return $this->hasMany(StudentGradeClass::class, 'std_id', 'std_id');
    }
}
