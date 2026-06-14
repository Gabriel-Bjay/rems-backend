<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 255);
            $table->text('description')->nullable();
            $table->decimal('rent_amount', 10, 2);
            $table->enum('status', ['vacant', 'occupied'])->default('vacant');

            $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained('tenants')->nullOnDelete();

            $table->timestamps();
        });
        DB::statement('ALTER TABLE apartments ADD CONSTRAINT apartments_rent_amount_check CHECK (rent_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('apartments');
    }
};
