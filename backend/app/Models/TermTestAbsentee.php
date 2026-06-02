<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTestAbsentee extends Model
{
    protected $table = 'term_test_absentees_tbl';
    protected $primaryKey = 'absent_id';
    public $timestamps = true;

    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_updated';

    protected $guarded = [];
}
