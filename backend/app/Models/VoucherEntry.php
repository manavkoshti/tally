<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherEntry extends Model
{
    protected $fillable = [
        'voucher_id', 'ledger_id', 'entry_type', 'amount',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'narration', 'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
    ];

    public function voucher() { return $this->belongsTo(Voucher::class); }
    public function ledger() { return $this->belongsTo(Ledger::class); }
}
