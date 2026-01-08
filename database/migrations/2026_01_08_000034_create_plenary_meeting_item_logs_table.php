<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plenary_meeting_item_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plenary_meeting_item_id')->constrained('plenary_meeting_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenary_meeting_item_logs');
    }
};
