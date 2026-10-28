<?php

return [
    /*
    | Reverse proxies whose X-Forwarded-* headers are trusted, as a
    | comma-separated list of IPs or CIDR ranges. Needed when the app sits
    | behind https termination (e.g. a Tailscale Funnel to the dev nginx),
    | so links and redirects keep the public https host. Empty trusts none.
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
