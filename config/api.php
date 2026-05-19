<?php

return [
    'rate_limit' => env('API_RATE_LIMIT', 100),
    'keys' => explode(',', env('API_KEYS', 'dev-key-change-me-in-production')),
];
