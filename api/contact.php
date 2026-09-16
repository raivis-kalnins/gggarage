<?php
// GG Garage repair request endpoint.
// Written to be compatible with PHP 5.6+ shared hosting.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function gg_value($array, $key, $default) {
    return (is_array($array) && array_key_exists($key, $array)) ? $array[$key] : $default;
}

function respond($status, $ok, $message, $extra) {
    http_response_code((int)$status);
    $payload = array_merge(array('ok' => (bool)$ok, 'message' => (string)$message), is_array($extra) ? $extra : array());
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (gg_value($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
    respond(405, false, 'Method not allowed.', array());
}

$configFile = dirname(__DIR__) . '/config.php';
$config = is_file($configFile) ? require $configFile : array();
$recipient = (string)gg_value($config, 'recipient_email', 'gggarage@gmail.com');
$mailFrom = (string)gg_value($config, 'mail_from', 'website@gggarage.lv');
$mailEnabled = (bool)gg_value($config, 'mail_enabled', true);
$saveSubmissions = (bool)gg_value($config, 'save_submissions', true);
$rateLimitSeconds = max(0, (int)gg_value($config, 'rate_limit_seconds', 25));
$storageDir = dirname(__DIR__) . '/storage';

$lang = (gg_value($_POST, 'lang', 'lv') === 'en') ? 'en' : 'lv';
$allMessages = array(
    'lv' => array(
        'invalid' => 'Lūdzu, pārbaudiet obligātos laukus un mēģiniet vēlreiz.',
        'fast' => 'Lūdzu, uzgaidiet brīdi un mēģiniet vēlreiz.',
        'success' => 'Pieteikums saņemts.',
        'server' => 'Pieteikumu neizdevās nosūtīt. Lūdzu, sazinieties ar servisu pa tālruni.'
    ),
    'en' => array(
        'invalid' => 'Please check the required fields and try again.',
        'fast' => 'Please wait a moment and try again.',
        'success' => 'Request received.',
        'server' => 'We could not send your request. Please contact the service centre by phone.'
    )
);
$messages = $allMessages[$lang];

// Honeypot: real users never fill this field.
if (trim((string)gg_value($_POST, 'website', '')) !== '') {
    respond(200, true, $messages['success'], array());
}

// Timestamp is refreshed by JS when the static page loads.
$startedRaw = gg_value($_POST, 'form_started', '');
$started = filter_var($startedRaw, FILTER_VALIDATE_INT);
$now = time();
if (!$started || ($now - (int)$started) < 2 || ($now - (int)$started) > 86400) {
    respond(422, false, $messages['invalid'], array());
}

function limit_text($value, $max) {
    $value = (string)$value;
    $max = (int)$max;
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function field_value($key, $max) {
    $value = trim((string)gg_value($_POST, $key, ''));
    $cleaned = preg_replace('/\s+/u', ' ', $value);
    if ($cleaned !== null) {
        $value = $cleaned;
    }
    return limit_text($value, $max);
}

$name = field_value('name', 120);
$phone = field_value('phone', 40);
$email = field_value('email', 160);
$service = field_value('service', 160);
$equipment = field_value('equipment', 160);
$brandModel = field_value('brand_model', 180);
$issue = trim((string)gg_value($_POST, 'issue', ''));
$issue = limit_text($issue, 3000);
$contactMethod = (gg_value($_POST, 'contact_method', 'phone') === 'email') ? 'email' : 'phone';
$consent = gg_value($_POST, 'consent', '') === '1';

if ($name === '' || $phone === '' || $service === '' || $equipment === '' || $issue === '' || !$consent) {
    respond(422, false, $messages['invalid'], array());
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, $messages['invalid'], array());
}
if ($contactMethod === 'email' && $email === '') {
    respond(422, false, $messages['invalid'], array());
}

// Lightweight per-IP rate limit. Only a hash is stored.
if ($rateLimitSeconds > 0) {
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    $ip = (string)gg_value($_SERVER, 'REMOTE_ADDR', 'unknown');
    $ipHash = hash('sha256', $ip . '|gggarage-rate-v1');
    $rateFile = $storageDir . '/.rate-' . substr($ipHash, 0, 24);
    if (is_file($rateFile)) {
        $last = (int)@file_get_contents($rateFile);
        if ($last > 0 && ($now - $last) < $rateLimitSeconds) {
            respond(429, false, $messages['fast'], array());
        }
    }
    @file_put_contents($rateFile, (string)$now, LOCK_EX);
}

function make_request_id() {
    if (function_exists('random_bytes')) {
        try {
            return strtoupper(bin2hex(random_bytes(4)));
        } catch (Exception $e) {
            // Fall through to the portable fallback below.
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes = @openssl_random_pseudo_bytes(4, $strong);
        if ($bytes !== false && strlen($bytes) === 4) {
            return strtoupper(bin2hex($bytes));
        }
    }
    return strtoupper(substr(hash('sha256', uniqid('', true) . mt_rand()), 0, 8));
}

$requestId = make_request_id();
$record = array(
    'id' => $requestId,
    'created_at' => gmdate('c'),
    'lang' => $lang,
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'service' => $service,
    'equipment' => $equipment,
    'brand_model' => $brandModel,
    'issue' => $issue,
    'preferred_contact' => $contactMethod,
);

$stored = false;
if ($saveSubmissions) {
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    // Store the fallback log in a PHP-guarded file so it cannot be read from the web.
    $storageFile = $storageDir . '/requests.php';
    if (!is_file($storageFile)) {
        @file_put_contents($storageFile, "<?php http_response_code(404); exit; ?>\n", LOCK_EX);
    }
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $stored = @file_put_contents($storageFile, $line, FILE_APPEND | LOCK_EX) !== false;
}

$mailSent = false;
if ($mailEnabled && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $subject = 'GG Garage servisa pieteikums #' . $requestId;
    $body = "Jauns servisa pieteikums no gggarage.lv\n\n"
        . "Pieteikuma ID: " . $requestId . "\n"
        . "Vārds: " . $name . "\n"
        . "Tālrunis: " . $phone . "\n"
        . "E-pasts: " . ($email !== '' ? $email : '-') . "\n"
        . "Pakalpojums: " . $service . "\n"
        . "Tehnikas veids: " . $equipment . "\n"
        . "Zīmols/modelis: " . ($brandModel !== '' ? $brandModel : '-') . "\n"
        . "Vēlamais saziņas veids: " . $contactMethod . "\n\n"
        . "Problēmas apraksts:\n" . $issue . "\n";

    $safeFrom = preg_replace('/[\r\n]+/', '', $mailFrom);
    if (!$safeFrom) {
        $safeFrom = 'website@gggarage.lv';
    }
    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: GG Garage Website <' . $safeFrom . '>',
    );
    if ($email !== '') {
        $safeReply = preg_replace('/[\r\n]+/', '', $email);
        if ($safeReply) {
            $headers[] = 'Reply-To: ' . $safeReply;
        }
    }

    $encodedSubject = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
        : $subject;
    $mailSent = @mail($recipient, $encodedSubject, $body, implode("\r\n", $headers));
}

if (!$stored && !$mailSent) {
    respond(500, false, $messages['server'], array());
}

respond(200, true, $messages['success'], array('id' => $requestId));
