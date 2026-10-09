<?php

return [
    'minimum_seconds' => (int) env('CONTACT_MINIMUM_SECONDS', 3),
    'maximum_seconds' => (int) env('CONTACT_MAXIMUM_SECONDS', 3600),
    'hourly_limit' => (int) env('CONTACT_HOURLY_LIMIT', 5),
];
