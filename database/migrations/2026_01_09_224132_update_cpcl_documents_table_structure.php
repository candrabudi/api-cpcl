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
        Schema::table('cpcl_documents', function (Blueprint $table) {
            $table->dropColumn(['document_number', 'document_date']);
            
            $table->string('title')->after('id');
            $table->string('program_code')->unique()->after('title');
            $table->year('year')->after('program_code');
            $table->date('cpcl_date')->after('year');
            $table->integer('cpcl_month')->after('cpcl_date');
            $table->foreignId('prepared_by')->after('cpcl_month')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cpcl_documents', function (Blueprint $table) {
            $table->dropForeign(['prepared_by']);
            $table->dropColumn(['title', 'program_code', 'year', 'cpcl_date', 'cpcl_month', 'prepared_by']);
            
            $table->string('document_number')->unique()->after('id');
            $table->date('document_date')->after('document_number');
        });
    }
};
