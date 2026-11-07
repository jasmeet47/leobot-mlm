<?php

return [
    // Fail closed. Enabling any financial operation requires a separate review.
    'member_activation_enabled' => false,
    'admin_member_activation_enabled' => (bool) env('LUMIFLOWON_ADMIN_MEMBER_ACTIVATION_ENABLED', false),
    'admin_profit_verification_enabled' => (bool) env('LUMIFLOWON_ADMIN_PROFIT_VERIFICATION_ENABLED', false),
    'magic_pool_funding_enabled' => false,
    'admin_unified_funding_ui_enabled' => (bool) env('LUMIFLOWON_ADMIN_UNIFIED_FUNDING_UI_ENABLED', false),
    'production_financial_approval' => env('LUMIFLOWON_PRODUCTION_FINANCIAL_APPROVAL', false),
    'staking_enabled' => env('LUMIFLOWON_STAKING_ENABLED', false),
    'self_income_settlement_enabled' => (bool) env('LUMIFLOWON_SELF_INCOME_SETTLEMENT_ENABLED', false),
    'generation_income_settlement_enabled' => (bool) env('LUMIFLOWON_GENERATION_INCOME_SETTLEMENT_ENABLED', false),
    'withdrawals_enabled' => env('LUMIFLOWON_WITHDRAWALS_ENABLED', false),
    'deposits_enabled' => env('LUMIFLOWON_DEPOSITS_ENABLED', false),
    'staking_profit_enabled' => env('LUMIFLOWON_STAKING_PROFIT_ENABLED', false),
    'withdrawal_settings_write_enabled' => env('LUMIFLOWON_WITHDRAWAL_POLICY_ENABLED', false),
];
