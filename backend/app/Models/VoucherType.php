<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherType extends Model
{
    protected $fillable = ['company_id', 'name', 'type', 'prefix', 'suffix', 'starting_number', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function company() { return $this->belongsTo(Company::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
}
