<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_field_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_field_id')->constrained('group_fields')->cascadeOnDelete();
            $table->string('label');
            $table->json('value')->nullable();
            $table->enum('row_type', [
                'header',
                'text',
                'number',
                'textarea',
                'select',
                'checkbox',
                'radio',
                'composite',
                'static',
            ])->default('text');
            $table->boolean('is_required')->default(false);
            $table->foreignId('parent_id')->nullable()->references('id')->on('group_field_rows')->nullOnDelete();
            $table->integer('order_no')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['group_field_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_field_rows');
    }
};
