<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue', 'void'])->default('unpaid');
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('replaced_by_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
            $table->index('tenancy_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
