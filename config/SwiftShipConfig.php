<?php

return [
    'default' => 'tcs',
    'tcs_client_id' => env('SWIFTSHIP_TCS_CLIENT_ID',''),
    'tcs_base_url' => env('SWIFTSHIP_TCS_BASE_URL',''),
    'tcs_username' => env('SWIFTSHIP_TCS_USERNAME', ''),
    'tcs_password' => env('SWIFTSHIP_TCS_PASSWORD',''),
    'tcs_tracking_url' => env('SWIFTSHIP_TCS_TRACKING_URL', ''),
];
