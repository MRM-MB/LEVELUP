<?php

return [
    'base_url' => env('WIFI2BLE_BASE_URL', 'http://simulator:8000'),
    'api_key' => env('WIFI2BLE_API_KEY'),
    'timeout' => (int) env('WIFI2BLE_TIMEOUT', 5),
];
