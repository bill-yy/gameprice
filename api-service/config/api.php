<?php

return [
    'rate_limit' => (int) env('API_RATE_LIMIT', 100),
    'keys' => array_filter(explode(',', env('API_KEYS', 'dev-key-change-me-in-production'))),
];
