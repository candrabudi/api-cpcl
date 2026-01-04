<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')
                  ->constrained('vendors')
                  ->cascadeOnDelete();
            $table->foreignId('document_type_id')
                  ->constrained('document_types')
                  ->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
