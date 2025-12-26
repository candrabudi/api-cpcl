<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_applicants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cpcl_document_id')
                ->constrained('cpcl_documents')
                ->cascadeOnDelete();

            $table->foreignId('cooperative_id')
                ->nullable()
                ->constrained('cooperatives')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_applicants');
    }
};
