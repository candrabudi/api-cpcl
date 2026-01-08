<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpcl_document_id')->constrained('cpcl_documents')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('nik', 16)->unique();
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_applicants');
    }
};
