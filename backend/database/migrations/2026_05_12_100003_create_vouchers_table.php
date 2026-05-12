<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['sales', 'purchase', 'payment', 'receipt', 'contra', 'journal'])->default('journal');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->integer('starting_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('voucher_type_id')->nullable()->constrained('voucher_types')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('voucher_number')->nullable();
            $table->enum('voucher_type', ['sales', 'purchase', 'payment', 'receipt', 'contra', 'journal'])->default('journal');
            $table->date('voucher_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('narration')->nullable();
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->enum('tally_sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->string('tally_voucher_number')->nullable();
            $table->timestamp('tally_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'voucher_type']);
            $table->index(['company_id', 'tally_sync_status']);
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('ledgers')->cascadeOnDelete();
            $table->enum('entry_type', ['debit', 'credit'])->default('debit');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->string('narration')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('voucher_types');
    }
};
