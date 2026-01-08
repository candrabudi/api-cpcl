<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpcl_document_id')->constrained('cpcl_documents')->cascadeOnDelete();
            $table->foreignId('group_field_row_id')->constrained('group_field_rows')->cascadeOnDelete();
            $table->json('answer_value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_answers');
    }
};
