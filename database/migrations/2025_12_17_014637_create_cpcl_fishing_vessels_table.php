<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_fishing_vessels', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('cpcl_document_id');

            $table->string('ship_type')->nullable();
            $table->string('engine_brand')->nullable();
            $table->string('engine_power')->nullable();

            $table->unsignedInteger('quantity')->default(0);

            $table->timestamps();

            $table->foreign('cpcl_document_id')
                ->references('id')
                ->on('cpcl_documents')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_fishing_vessels');
    }
};
