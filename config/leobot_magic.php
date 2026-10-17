<?php

// Dedicated default-OFF Magic Matrix safety switches.
// The project's existing config/leobot.php is intentionally NOT overwritten.
return [
    'magic_matrix_enrollment_enabled' => false,
    'magic_matrix_topup_enabled' => false,
    'magic_matrix_settlement_enabled' => false,
    'magic_matrix_settings_write_enabled' => false,
    'magic_matrix_rates_write_enabled' => true,
    'production_rates_approval' => true,
    'production_financial_approval' => false,
];
