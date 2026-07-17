<?php

return [

    'version' => '2.3.0',

    'default_admin' => [
        'username' => env('AUTOMATOR_ADMIN_USERNAME', 'admin'),
        'email' => env('AUTOMATOR_ADMIN_EMAIL', 'admin@localhost'),
        'password' => env('AUTOMATOR_ADMIN_PASSWORD', 'Admin1234!'),
    ],

    'default_operator' => [
        'username' => env('AUTOMATOR_OPERATOR_USERNAME', 'operator'),
        'email' => env('AUTOMATOR_OPERATOR_EMAIL', 'operator@localhost'),
        'password' => env('AUTOMATOR_OPERATOR_PASSWORD', 'Operator1234!'),
    ],

    'default_viewer' => [
        'username' => env('AUTOMATOR_VIEWER_USERNAME', 'viewer'),
        'email' => env('AUTOMATOR_VIEWER_EMAIL', 'viewer@localhost'),
        'password' => env('AUTOMATOR_VIEWER_PASSWORD', 'Viewer1234!'),
    ],

    // Protects the seeded admin account from self-demotion/self-deletion/username changes.
    'protected_admin_username' => env('AUTOMATOR_ADMIN_USERNAME', 'admin'),

    'execution' => [
        'timeout_seconds' => env('AUTOMATOR_EXECUTION_TIMEOUT_SECONDS', 300),
        'max_history_records' => env('AUTOMATOR_MAX_HISTORY_RECORDS', 1000),
    ],

];
