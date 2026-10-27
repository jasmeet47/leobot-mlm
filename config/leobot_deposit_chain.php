<?php
// Fail closed. Configure ONLY trusted company receiving addresses in Render secrets.
return [
    'enforce_on_chain' => env('LEOBOT_DEPOSIT_ENFORCE_ON_CHAIN', false),
    'recipients' => [
        'TRC20' => env('LEOBOT_COMPANY_TRC20_ADDRESS', ''),
        'BEP20' => env('LEOBOT_COMPANY_BEP20_ADDRESS', ''),
    ],
];
