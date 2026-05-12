<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'invoice_id', 'voucher_type_id', 'created_by',
        'voucher_number', 'voucher_type', 'voucher_date', 'amount', 'narration',
        'status', 'tally_sync_status', 'tally_voucher_number', 'tally_synced_at',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'tally_synced_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->uuid = Str::uuid());
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function voucherType() { return $this->belongsTo(VoucherType::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function entries() { return $this->hasMany(VoucherEntry::class); }
    public function tallySyncLogs() { return $this->hasMany(TallySyncLog::class); }
}
