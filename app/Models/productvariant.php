<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class productvariant extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'price',
        'stock_quantity',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Tự động chuẩn hóa chuỗi màu người dùng nhập (xóa khoảng trắng thừa, viết hoa chữ cái đầu)
     */
    protected function color(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $clean = mb_strtolower(trim($value), 'UTF-8');
                return mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8')
                     . mb_substr($clean, 1, null, 'UTF-8');
            }
        );
    }

    /**
     * Tự động chuẩn hóa kích cỡ (ví dụ: " m " -> "M")
     */
    protected function size(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper(trim($value), 'UTF-8')
        );
    }

    /**
     * Lấy giá bán thực tế: ưu tiên giá riêng của biến thể, nếu null thì lấy giá gốc sản phẩm
     */
    public function getEffectivePriceAttribute(): string
    {
        return $this->price ?? $this->product->base_price;
    }
}
