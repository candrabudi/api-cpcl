<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plenary_meeting_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plenary_meeting_id')->constrained('plenary_meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created, updated, deleted
            $table->json('changes')->nullable(); // field yang berubah
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenary_meeting_logs');
    }
};
