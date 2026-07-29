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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount_required', 10, 2);
            $table->decimal('amount_held', 10, 2)->default(0);
            $table->enum('status', ['pending', 'held', 'settled', 'refunded'])->default('pending');
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
