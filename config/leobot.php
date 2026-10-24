<?php
return [
    'registration_otp_demo_default' => env('LEOBOT_DEMO_REGISTRATION_OTP_DEFAULT', true),
    'fund_transfers_enabled' => false,
    // Fail closed. Enabling any financial operation requires a separate review.
    'member_activation_enabled' => false,
    'minimum_activation_usdt' => '10',
    'magic_pool_funding_enabled' => false,
    'admin_unified_funding_ui_enabled' => false,
    'production_financial_approval' => false,
    'self_income_settlement_enabled' => false,
    'generation_income_settlement_enabled' => false,
    'withdrawals_enabled' => false,
    'withdrawal_settings_write_enabled' => false,
];
