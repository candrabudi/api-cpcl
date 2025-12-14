<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cpcl_documents', function (Blueprint $table) {
            $table->id();

            $table->string('cpcl_number')->unique()->nullable();

            $table->string('title');

            $table->string('program_code')->nullable();

            $table->year('year');

            $table->date('cpcl_date');

            $table->unsignedTinyInteger('cpcl_month');

            $table->enum('status', [
                'draft',
                'submitted',
                'review',
                'pleno',
                'approved',
                'rejected',
                'archived',
            ])->default('draft');

            $table->enum('pleno_result', [
                'pending',
                'approved',
                'revision',
                'rejected',
            ])->default('pending');

            $table->unsignedInteger('version')->default(1);

            $table->date('submitted_date')->nullable();

            $table->date('pleno_date')->nullable();

            $table->text('pleno_notes')->nullable();

            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpcl_documents');
    }
};
