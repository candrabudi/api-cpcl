<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('handover_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_id')->nullable();
            $table->unsignedBigInteger('cooperative_id')->nullable();
            $table->string('report_number')->unique();
            $table->date('handover_date');
            
            // PIHAK KESATU (Giver - Government)
            $table->string('first_party_name');
            $table->string('first_party_nip')->nullable();
            $table->string('first_party_position');
            $table->text('first_party_address')->nullable();
            
            // PIHAK KEDUA (Receiver - Cooperative)
            $table->string('second_party_name');
            $table->string('second_party_position');
            $table->text('second_party_address')->nullable();
            
            // Location/Coordinates
            $table->text('location_description')->nullable();
            $table->double('latitude', 10, 8)->nullable();
            $table->double('longitude', 11, 8)->nullable();
            
            $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('procurement_id')->references('id')->on('procurements')->onDelete('cascade');
            $table->foreign('cooperative_id')->references('id')->on('cooperatives')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_certificates');
    }
};
