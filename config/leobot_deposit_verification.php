<?php

return [
    // OFF until verified official receiving addresses and trusted RPC are ready.
    'enabled' => false,
    'receiving_addresses' => [
        'TRC20' => env('LEOBOT_DEPOSIT_TRC20_ADDRESS', ''),
        'BEP20' => env('LEOBOT_DEPOSIT_BEP20_ADDRESS', ''),
    ],
];