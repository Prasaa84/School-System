<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTestResult extends Model
{
    protected $table = 'term_test_results_tbl';
    protected $primaryKey = 'result_id';
    public $timestamps = true;

    public const CREATED_AT = 'date_added';
    public const UPDATED_AT = 'date_updated';

    protected $guarded = [];
}
