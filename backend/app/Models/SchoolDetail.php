<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolDetail extends Model
{
    protected $table = 'school_details_tbl';
    protected $primaryKey = 'census_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];
}
