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
        Schema::create('productvariants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('size', 20); // Người dùng tự nhập: S, M, L, FreeSize, 29, 30...
            $table->string('color', 50)->index(); // Người dùng tự nhập tự do: Trắng, Xanh bơ, Be sữa...
            $table->decimal('price', 12, 2)->nullable(); // Giá riêng nếu biến thể chênh lệch giá gốc
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image')->nullable(); // Ảnh sản phẩm ứng với màu này
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productvariants');
    }
};
