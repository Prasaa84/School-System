<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeSpan extends Model
{
    protected $table = 'grade_span_tbl';
    protected $primaryKey = 'grd_span_id';
    public $timestamps = false;

    protected $guarded = [];
}
