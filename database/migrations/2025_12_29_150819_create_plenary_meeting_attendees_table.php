<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plenary_meeting_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plenary_meeting_id')
                ->constrained('plenary_meetings')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('work_unit');
            $table->string('position');
            $table->string('signature')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenary_meeting_attendees');
    }
};
