<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallySyncLog extends Model
{
    protected $fillable = [
        'company_id', 'voucher_id', 'ledger_id', 'sync_type', 'status',
        'xml_request', 'xml_response', 'error_message', 'tally_host', 'tally_port',
        'response_code', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function voucher() { return $this->belongsTo(Voucher::class); }
    public function ledger() { return $this->belongsTo(Ledger::class); }
}
