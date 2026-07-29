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
        Schema::create('vacate_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenancy_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->date('intended_move_out_date');
            $table->enum('status', ['submitted', 'acknowledged', 'completed', 'cancelled'])->default('submitted');
            $table->timestamps();
            $table->index('tenancy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacate_notices');
    }
};
