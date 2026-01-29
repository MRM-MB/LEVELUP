<?php

return [
    'base_url' => env(
        'WIFI2BLE_BASE_URL',
        env('WIFI2BLE_HOSTPORT')
            ? 'http://' . env('WIFI2BLE_HOSTPORT')
            : (env('WIFI2BLE_HOST') ? 'https://' . env('WIFI2BLE_HOST') : 'http://simulator:8000')
    ),
    'api_key' => env('WIFI2BLE_API_KEY'),
    'timeout' => (int) env('WIFI2BLE_TIMEOUT', 5),
];
