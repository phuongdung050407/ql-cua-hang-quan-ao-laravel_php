<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gross price as base price
    |--------------------------------------------------------------------------
    |
    | This default value is used to select the method to calculate prices and taxes
    | If true the item price is managed as a gross price, so taxes will be calculated by separation/exclusion
    |
    */

    'calculator' => \Gloudemans\Shoppingcart\Calculation\DefaultCalculator::class,

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 0,

    /*
    |--------------------------------------------------------------------------
    | Shoppingcart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shoppingcart should use when
    | storing and restoring a cart.
    |
    */

    'database' => [

        'connection' => null,

        'table' => 'carts',

    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formatted numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [
        'decimals' => 0,
        'decimal_point' => ',',
        'thousand_seperator' => '.',
        'currency_symbol' => 'đ',
    ],

    // Tham số điều chỉnh thuật toán gợi ý sản phẩm theo lịch sử tìm kiếm
    'recommendation' => [
        'history_days' => 30,       // Lấy lịch sử tìm kiếm trong 30 ngày gần nhất
        'max_histories' => 20,      // Số truy vấn gần nhất đưa vào tính điểm
        'default_limit' => 8,       // Số sản phẩm gợi ý hiển thị trên trang chủ
        'weights' => [
            'keyword_match' => 3.0,
            'variant_color' => 2.5,
            'category_match' => 2.0,
            'clicked_product' => 2.0,
            'gender_match' => 1.5,
        ],
],
];
