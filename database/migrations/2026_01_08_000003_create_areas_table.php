<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('province_id', 10)->nullable();
            $table->string('province_name')->nullable();
            $table->string('city_id', 10)->nullable();
            $table->string('city_name')->nullable();
            $table->string('district_id', 10)->nullable();
            $table->string('district_name')->nullable();
            $table->string('sub_district_id', 10)->nullable();
            $table->string('sub_district_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
