<?php

/*
| Audit log (audit_events) – security and administrative events.
|
| Retention: 730 days PROVISIONALLY. Must be confirmed with the
| Datenschutzbeauftragte before launch (docs/privacy.md). This applies only
| to audit events, NOT to future content revisions/versions, which will get
| their own retention rules.
*/

return [

    // Days to keep audit events. 0 disables automatic deletion.
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 730),

    // Expired events are deleted opportunistically when new events are written
    // (chance = numerator/denominator), so no cron job is required.
    'prune_lottery' => [1, 50],

    // Maximum rows deleted per opportunistic run (keeps requests fast).
    'prune_batch_size' => 1000,

];
