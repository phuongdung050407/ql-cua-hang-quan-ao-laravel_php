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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('material', 100)->nullable(); // Chất liệu: Cotton, Kaki, Linen...
            $table->enum('gender', ['male', 'female', 'unisex'])->default('unisex');
            $table->decimal('base_price', 12, 2);
            $table->json('tags')->nullable(); // Lưu từ khóa đặc trưng: ["oversize", "công sở", "mùa hè"]
            $table->string('thumbnail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
