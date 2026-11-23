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
        Schema::table('report_members', function (Blueprint $table) {
            $table->text('readiness_note')->nullable()->after('signed');
        });

        Schema::table('report_decisions', function (Blueprint $table) {
            $table->integer('actual_paying_customers')->nullable()->after('min_mrr');
            $table->decimal('actual_mrr', 12, 2)->nullable()->after('actual_paying_customers');
            $table->json('decisions_list')->nullable()->after('expansion_text_am');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_members', function (Blueprint $table) {
            $table->dropColumn('readiness_note');
        });

        Schema::table('report_decisions', function (Blueprint $table) {
            $table->dropColumn(['actual_paying_customers', 'actual_mrr', 'decisions_list']);
        });
    }
};
