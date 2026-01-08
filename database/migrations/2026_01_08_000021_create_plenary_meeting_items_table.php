<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plenary_meeting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plenary_meeting_id')->constrained('plenary_meetings')->cascadeOnDelete();
            $table->foreignId('cooperative_id')->constrained('cooperatives');
            $table->foreignId('cpcl_document_id')->nullable()->constrained('cpcl_documents')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->integer('package_quantity');
            $table->string('note')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenary_meeting_items');
    }
};
