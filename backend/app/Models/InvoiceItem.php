<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'ledger_id', 'description', 'hsn_sac', 'quantity', 'unit',
        'rate', 'amount', 'discount', 'taxable_amount', 'gst_rate',
        'cgst_rate', 'sgst_rate', 'igst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function ledger() { return $this->belongsTo(Ledger::class); }
}
