<?php

return [
    'default' => 'tcs',
    'tcs' => [
        'tcs_client_id' => env('SWIFTSHIP_TCS_CLIENT_ID',''),
        'tcs_base_url' => env('SWIFTSHIP_TCS_BASE_URL',''),
        'tcs_username' => env('SWIFTSHIP_TCS_USERNAME', ''),
        'tcs_password' => env('SWIFTSHIP_TCS_PASSWORD',''),
        'tcs_tracking_url' => env('SWIFTSHIP_TCS_TRACKING_URL', ''),
    ],
    'lcs' => [
        'lcs_mode' => env('SWIFTSHIP_LCS_MODE', 'sandbox'),
        'lcs_staging_url' => env('SWIFTSHIP_LCS_STAGING_URL','https://merchantapistaging.leopardscourier.com/api/'),
        'lcs_production_url' => env('SWIFTSHIP_LCS_PRODUCTION_URL','https://merchantapi.leopardscourier.com/api/'),
        'lcs_password' => env('SWIFTSHIP_LCS_PASSWORD',''),
        'lcs_tracking_url' => env('SWIFTSHIP_LCS_TRACKING_URL', ''),
        'lcs_api_key' => env('SWIFTSHIP_LCS_API_KEY',''),
        'lcs_api_version' => env('SWIFTSHIP_LCS_API_VERSION','v1'),
    ]
];
