<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeSubject extends Model
{
    protected $table = 'subjects_grade_tbl';
    protected $primaryKey = 'subj_grd_id';
    public $timestamps = true;

    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_updated';

    protected $guarded = [];
}
