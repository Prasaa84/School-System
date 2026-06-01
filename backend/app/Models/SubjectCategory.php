<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectCategory extends Model
{
    protected $table = 'subject_category_tbl';
    protected $primaryKey = 'sub_cat_id';
    public $timestamps = false;

    protected $guarded = [];
}
