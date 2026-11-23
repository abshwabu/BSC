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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_member_id')->constrained('report_members')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->text('activity_text');
            $table->text('kpi_text');
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('target_label')->nullable(); // e.g. "60 ማሳያዎች"
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->string('actual_label')->nullable();
            $table->string('direction')->default('higher_better'); // higher_better | lower_better
            $table->string('perspective'); // financial | customers | internal | learning
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
