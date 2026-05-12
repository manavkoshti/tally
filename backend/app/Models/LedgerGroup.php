<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerGroup extends Model
{
    protected $fillable = ['company_id', 'name', 'parent_group', 'nature'];

    public function company() { return $this->belongsTo(Company::class); }
    public function ledgers() { return $this->hasMany(Ledger::class); }
}
