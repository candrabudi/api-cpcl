<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->decimal('allocated_budget', 20, 2)->default(0)->after('total_budget');
        });

        Schema::table('annual_budget_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_item_id')->nullable()->change();
            $table->string('type')->default('spending')->after('procurement_item_id'); // spending, allocation
            $table->string('notes')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('annual_budgets', function (Blueprint $table) {
            $table->dropColumn('allocated_budget');
        });

        Schema::table('annual_budget_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_item_id')->nullable(false)->change();
            $table->dropColumn(['type', 'notes']);
        });
    }
};
