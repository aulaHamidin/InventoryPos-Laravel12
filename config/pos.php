<?php

return [
    'pending_transaction_expiry_hours' => (int) env('POS_PENDING_TRANSACTION_EXPIRY_HOURS', 24),
    'bluetooth_print_enabled' => (bool) env('POS_BLUETOOTH_PRINT_ENABLED', false),
];
