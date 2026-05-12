<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'user_id', 'party_ledger_id', 'invoice_number', 'invoice_type',
        'invoice_date', 'party_name', 'party_gstin', 'file_path', 'file_type', 'ocr_status',
        'ocr_raw_data', 'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'total_gst_amount', 'total_amount', 'round_off', 'place_of_supply', 'is_interstate',
        'accounting_status', 'tally_sync_status', 'narration',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'ocr_raw_data' => 'array',
        'is_interstate' => 'boolean',
        'taxable_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->uuid = Str::uuid());
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function partyLedger() { return $this->belongsTo(Ledger::class, 'party_ledger_id'); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function gstDetails() { return $this->hasMany(GstDetail::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
}
