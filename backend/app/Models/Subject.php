<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subject_tbl';
    protected $primaryKey = 'subject_id';
    public $timestamps = false;

    protected $guarded = [];
}
