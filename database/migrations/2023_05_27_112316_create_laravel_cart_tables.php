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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid();
            $table->string('name');
            $table->unique(['tenant_id', 'uuid', 'name']);
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->references('id')->on('carts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->morphs('item');
            $table->string('title')->nullable()->default(null);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price')->default(0.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_contents');
        Schema::dropIfExists('carts');
    }
};
