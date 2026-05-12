<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ledger extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'ledger_group_id', 'name', 'alias', 'type',
        'gstin', 'pan', 'address', 'state', 'phone', 'email',
        'opening_balance', 'opening_balance_type', 'is_active', 'synced_to_tally', 'tally_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'synced_to_tally' => 'boolean',
        'opening_balance' => 'decimal:2',
        'tally_synced_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->uuid = Str::uuid());
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function group() { return $this->belongsTo(LedgerGroup::class, 'ledger_group_id'); }
    public function voucherEntries() { return $this->hasMany(VoucherEntry::class); }
}
