<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    protected $table = 'staff_tbl';
    protected $primaryKey = 'stf_id';
    public $timestamps = false;

    protected $guarded = [];

    public function school(): BelongsTo
    {
        return $this->belongsTo(SchoolDetail::class, 'census_id', 'census_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'desig_id', 'desig_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'gender_id');
    }
}
