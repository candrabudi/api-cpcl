<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_login_otps', function (Blueprint $table) {
            $table->id();

            $table->string('email')->index();
            $table->string('otp');
            $table->timestamp('expired_at');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_login_otps');
    }
};
