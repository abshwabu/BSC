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
        Schema::create('report_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->boolean('plc_gate_enabled')->default(false);
            $table->integer('min_paying_customers')->nullable();
            $table->decimal('min_mrr', 12, 2)->nullable();
            $table->text('decision_text_am')->nullable();
            $table->text('expansion_text_am')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_decisions');
    }
};
