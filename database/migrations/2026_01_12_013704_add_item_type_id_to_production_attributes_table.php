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
        Schema::table('production_attributes', function (Blueprint $table) {
            $table->foreignId('item_type_id')
                ->nullable()
                ->after('id')
                ->constrained('item_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_attributes', function (Blueprint $table) {
            $table->dropForeign(['item_type_id']);
            $table->dropColumn('item_type_id');
        });
    }
};
