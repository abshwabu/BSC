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
        Schema::create('activity_templates', function (Blueprint $table) {
            $table->id();
            $table->string('position_key')->default('general'); // sales, marketing, tech, general
            $table->string('title')->nullable();
            $table->text('activity_text');
            $table->text('kpi_text');
            $table->decimal('default_target_value', 12, 2)->nullable();
            $table->string('default_target_label')->nullable();
            $table->string('direction')->default('higher_better'); // higher_better, lower_better
            $table->string('perspective')->default('customers'); // financial, customers, internal, learning
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_templates');
    }
};
