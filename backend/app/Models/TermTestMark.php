<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTestMark extends Model
{
    protected $table = 'term_test_marks_tbl';
    protected $primaryKey = 'st_mrk_id';
    public $timestamps = true;

    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_updated';

    protected $guarded = [];
}
