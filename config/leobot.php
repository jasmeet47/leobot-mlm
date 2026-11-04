<?php

return [
    // Fail closed. Enabling any financial operation requires a separate review.
    'member_activation_enabled' => false,
    'magic_pool_funding_enabled' => false,
    'admin_unified_funding_ui_enabled' => false,
    'production_financial_approval' => env('LUMIFLOWON_PRODUCTION_FINANCIAL_APPROVAL', false),
    'staking_enabled' => env('LUMIFLOWON_STAKING_ENABLED', false),
    'self_income_settlement_enabled' => false,
    'generation_income_settlement_enabled' => false,
    'withdrawals_enabled' => env('LUMIFLOWON_WITHDRAWALS_ENABLED', false),
    'deposits_enabled' => env('LUMIFLOWON_DEPOSITS_ENABLED', false),
    'staking_profit_enabled' => env('LUMIFLOWON_STAKING_PROFIT_ENABLED', false),
    'withdrawal_settings_write_enabled' => env('LUMIFLOWON_WITHDRAWAL_POLICY_ENABLED', false),
];
