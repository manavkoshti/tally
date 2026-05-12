<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['company_id', 'key', 'value', 'type'];

    public function company() { return $this->belongsTo(Company::class); }
}
