<?php

return [
    'statistics_enabled' => (bool) env('SEARCH_STATISTICS_ENABLED', false),
    'retention_days' => (int) env('SEARCH_STATISTICS_RETENTION_DAYS', 30),
];
