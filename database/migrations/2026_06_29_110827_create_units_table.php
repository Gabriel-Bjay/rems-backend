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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 50)->nullable();
            $table->decimal('base_rent', 10, 2);
            $table->enum('status', ['vacant', 'occupied', 'under_maintenance'])->default('vacant');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('property_id');
            $table->index('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
