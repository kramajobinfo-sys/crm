<?php

return [
    /*
    | Website visitor tracking (docs/VISITS_SCOPE.md).
    |
    | The ingest is the app's only public write endpoint; these bound what it will accept and
    | how long the data is kept.
    */

    // visits:prune deletes page views older than this, daily.
    'retention_days' => (int) env('VISITS_RETENTION_DAYS', 180),

    // Beacon rate limits, per site key + hashed IP. Page views fire per page; identify fires
    // per form submit — completely different legitimate frequencies, so separate limits.
    'rate_limit' => [
        'pageview_per_minute' => (int) env('VISITS_PAGEVIEW_RATE', 120),
        'identify_per_minute' => (int) env('VISITS_IDENTIFY_RATE', 6),
    ],
];
