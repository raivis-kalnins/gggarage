<?php
return array(
    // Main destination for repair requests.
    'recipient_email' => 'gggarage@gmail.com',

    // Change this if your hosting provider requires another sender address.
    'mail_from' => 'website@gggarage.lv',
    'mail_enabled' => true,

    // A local copy is stored as NDJSON in /storage as a fallback/audit trail.
    'save_submissions' => true,

    // Basic anti-spam rate limit per IP address.
    'rate_limit_seconds' => 25,
);
