<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GstDetail extends Model
{
    protected $fillable = [
        'invoice_id', 'gst_rate', 'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total_tax',
    ];

    protected $casts = [
        'gst_rate' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_tax' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
