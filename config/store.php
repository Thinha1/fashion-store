<?php

return [
    'shipping_fee' => (int) env('STORE_SHIPPING_FEE', 30000),

    /*
    | The shop's bank account for bank-transfer orders. Bank transfer is only
    | offered at checkout once bank_id and account_number are both set.
    | bank_id is the VietQR bank code or BIN, e.g. "VCB" or "970436".
    */
    'bank_transfer' => [
        'bank_id' => env('STORE_BANK_ID'),
        'account_number' => env('STORE_BANK_ACCOUNT_NUMBER'),
        'account_name' => env('STORE_BANK_ACCOUNT_NAME'),
    ],

    /*
    | Decart's Anywear live try-on widget, loaded on product pages only. Leave
    | empty to keep it off. The value is the shop domain registered with
    | Anywear, e.g. "shop.example.com".
    */
    'anywear_domain' => env('ANYWEAR_DOMAIN'),
];
