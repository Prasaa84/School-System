<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDailyAttendance extends Model
{
    protected $table = 'student_daily_attendance_tbl';
    protected $primaryKey = 'attendance_id';
    public $timestamps = false;

    protected $guarded = [];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'std_id', 'std_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id', 'user_id');
    }
}
