<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTestMarksConfirm extends Model
{
    protected $table = 'term_test_marks_confirm_tbl';
    protected $primaryKey = 'marks_conf_id';
    public $timestamps = true;

    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_updated';

    protected $guarded = [];
}
