<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpcl_fishing_vessels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpcl_document_id')->constrained('cpcl_documents')->cascadeOnDelete();
            $table->string('vessel_name');
            $table->string('owner_name');
            $table->string('gt_volume')->nullable();
            $table->string('vessel_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpcl_fishing_vessels');
    }
};
