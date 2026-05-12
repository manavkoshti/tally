<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('parent_group')->nullable();
            $table->enum('nature', ['assets', 'liabilities', 'income', 'expense'])->nullable();
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_group_id')->nullable()->constrained('ledger_groups')->nullOnDelete();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->enum('type', ['debtor', 'creditor', 'bank', 'cash', 'income', 'expense', 'gst', 'other'])->default('other');
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->text('address')->nullable();
            $table->string('state')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');
            $table->boolean('is_active')->default(true);
            $table->boolean('synced_to_tally')->default(false);
            $table->timestamp('tally_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'gstin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
        Schema::dropIfExists('ledger_groups');
    }
};
