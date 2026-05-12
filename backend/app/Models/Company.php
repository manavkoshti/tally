<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'gstin', 'pan', 'address', 'city', 'state', 'pincode',
        'phone', 'email', 'tally_company_name', 'tally_host', 'tally_port', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tally_port' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->uuid = Str::uuid());
    }

    public function users() { return $this->hasMany(User::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function ledgers() { return $this->hasMany(Ledger::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
    public function tallySyncLogs() { return $this->hasMany(TallySyncLog::class); }
    public function settings() { return $this->hasMany(Setting::class); }
}
