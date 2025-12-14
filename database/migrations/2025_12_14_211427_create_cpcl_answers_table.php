<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_answers', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('cpcl_document_id');

            $table->bigInteger('cpcl_applicant_id');

            $table->bigInteger('group_field_row_id');

            // JAWABAN AKTUAL USER
            $table->json('value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_answers');
    }
};
