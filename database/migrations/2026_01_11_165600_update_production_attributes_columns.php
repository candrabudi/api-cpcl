<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_attributes', function (Blueprint $table) {
            if (!Schema::hasColumn('production_attributes', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('description');
            }
            if (!Schema::hasColumn('production_attributes', 'default_percentage')) {
                $table->integer('default_percentage')->default(0)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_attributes', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'default_percentage']);
        });
    }
};
