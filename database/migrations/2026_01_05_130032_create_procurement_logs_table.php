<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created, updated, deleted
            $table->json('changes')->nullable(); // field yang berubah
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_logs');
    }
};
