<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('type'); // hapus enum lama
            $table->foreignId('item_type_id')
                  ->nullable()
                  ->after('name')
                  ->constrained('item_types')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['item_type_id']);
            $table->dropColumn('item_type_id');
            $table->enum('type', ['machine', 'equipment', 'goods', 'ship', 'other'])->after('name');
        });
    }
};
