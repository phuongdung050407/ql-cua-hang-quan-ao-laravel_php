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
        Schema::create('searchhistories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index(); // Lưu lịch sử cả khi khách chưa đăng nhập
            $table->string('keyword'); // Từ khóa gốc khách nhập: "Áo Thun Trắng"
            $table->string('normalized_keyword')->index(); // Từ khóa đã chuẩn hóa: "ao thun trang"
            $table->foreignId('matched_category_id')
                  ->nullable()
                  ->constrained('categories')
                  ->nullOnDelete(); // Danh mục khớp nhất với truy vấn để mở rộng gợi ý
            $table->unsignedInteger('results_count')->default(0); // Theo dõi từ khóa không có kết quả để nhập hàng
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('searchhistories');
    }
};
