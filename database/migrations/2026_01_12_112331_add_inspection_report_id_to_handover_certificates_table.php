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
        Schema::table('handover_certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('inspection_report_id')->nullable()->after('procurement_id');
            $table->foreign('inspection_report_id')->references('id')->on('inspection_reports')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handover_certificates', function (Blueprint $table) {
            $table->dropForeign(['inspection_report_id']);
            $table->dropColumn('inspection_report_id');
        });
    }
};
