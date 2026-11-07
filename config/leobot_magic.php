<?php

// Dedicated default-OFF Magic Matrix safety switches.
// The project's existing config/leobot.php is intentionally NOT overwritten.
return [
    'magic_matrix_enrollment_enabled' => (bool) env('LUMIFLOWON_MAGIC_MAGIC_MATRIX_ENROLLMENT_ENABLED', false),
    'magic_matrix_topup_enabled' => (bool) env('LUMIFLOWON_MAGIC_MAGIC_MATRIX_TOPUP_ENABLED', false),
    'magic_matrix_settlement_enabled' => (bool) env('LUMIFLOWON_MAGIC_MAGIC_MATRIX_SETTLEMENT_ENABLED', false),
    'magic_matrix_settings_write_enabled' => (bool) env('LUMIFLOWON_MAGIC_MAGIC_MATRIX_SETTINGS_WRITE_ENABLED', false),
    'magic_matrix_rates_write_enabled' => true,
    'production_rates_approval' => true,
    'production_financial_approval' => (bool) env('LUMIFLOWON_MAGIC_PRODUCTION_FINANCIAL_APPROVAL', false),
];
