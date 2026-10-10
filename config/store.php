<?php

return [
    'shipping_fee' => (int) env('STORE_SHIPPING_FEE', 30000),

    /*
    | The shop's bank account for bank-transfer orders. Bank transfer is only
    | offered at checkout once bank_id and account_number are both set.
    | bank_id is the VietQR bank code or BIN, e.g. "VCB" or "970436".
    | proof_wait_minutes: how long after placing the order the customer waits
    | for the SePay webhook before they may upload a transfer receipt instead.
    */
    'bank_transfer' => [
        'bank_id' => env('STORE_BANK_ID'),
        'account_number' => env('STORE_BANK_ACCOUNT_NUMBER'),
        'account_name' => env('STORE_BANK_ACCOUNT_NAME'),
        'proof_wait_minutes' => (int) env('STORE_PAYMENT_PROOF_WAIT_MINUTES', 15),
    ],
];
