<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $table = 'designation_tbl';
    protected $primaryKey = 'desig_id';
    public $timestamps = false;

    protected $guarded = [];
}
