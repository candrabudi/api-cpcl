<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_type_budgets', function (Blueprint $table) {
            $table->decimal('used_amount', 20, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('item_type_budgets', function (Blueprint $table) {
            $table->dropColumn('used_amount');
        });
    }
};
