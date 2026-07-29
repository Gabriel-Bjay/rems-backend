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
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenancy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raised_by_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('assigned_to_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('unit_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
