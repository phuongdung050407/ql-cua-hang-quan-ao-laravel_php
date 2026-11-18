<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE', ''),
        'hash_secret' => env('VNPAY_HASH_SECRET', ''),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', '/thanh-toan/vnpay-return'),
    ],

    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE', ''),
        'access_key' => env('MOMO_ACCESS_KEY', ''),
        'secret_key' => env('MOMO_SECRET_KEY', ''),
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    ],
];