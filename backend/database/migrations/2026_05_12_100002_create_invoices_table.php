<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->enum('invoice_type', ['sales', 'purchase', 'expense', 'journal', 'payment', 'receipt'])->default('sales');
            $table->date('invoice_date')->nullable();
            $table->string('party_name')->nullable();
            $table->string('party_gstin', 15)->nullable();
            $table->string('file_path')->nullable();
            $table->enum('file_type', ['pdf', 'image', 'manual'])->default('manual');
            $table->enum('ocr_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('ocr_raw_data')->nullable();
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_gst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('round_off', 15, 2)->default(0);
            $table->string('place_of_supply')->nullable();
            $table->boolean('is_interstate')->default(false);
            $table->enum('accounting_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('tally_sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('narration')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'invoice_type']);
            $table->index(['company_id', 'tally_sync_status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->string('description');
            $table->string('hsn_sac')->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('gst_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_details');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
