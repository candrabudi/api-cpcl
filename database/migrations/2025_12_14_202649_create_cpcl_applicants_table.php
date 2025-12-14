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

            $table->foreignId('area_id');

            $table->year('established_year')->nullable();

            $table->string('group_name');
            $table->string('cooperative_registration_number')->nullable();
            $table->string('kusuka_id_number')->nullable();

            $table->string('street_address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('regency')->nullable();
            $table->string('province')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('member_count')->default(0);

            $table->string('chairman_name')->nullable();
            $table->string('secretary_name')->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('chairman_phone_number')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_applicants');
    }
};
