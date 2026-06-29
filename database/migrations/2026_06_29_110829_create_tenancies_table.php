<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('drafted_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('status', ['draft', 'active', 'ended'])->default('draft');
            $table->timestamps();
            $table->index('unit_id');
            $table->index('tenant_id');
        });
        DB::statement("CREATE UNIQUE INDEX uniq_active_tenancy_per_unit ON tenancies (unit_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenancies');
    }
};
