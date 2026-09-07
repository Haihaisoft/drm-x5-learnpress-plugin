<?php
// DRM-X 5.0 license endpoint for WordPress and LearnPress.

// Relay a cross-site POST once before WordPress reads its login cookie. This
// changes the request into a same-site POST and works around SameSite=Lax.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST['_drmx5_same_site_relay'])) {
    $allowed = array(
        'profileid' => 255,
        'clientinfo' => 8192,
        'rightsid' => 255,
        'yourproductid' => 1024,
        'platform' => 255,
        'contenttype' => 255,
        'version' => 255,
        'return_url' => 2048,
        'mac' => 1024,
    );
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>DRM-X 5.0</title></head><body>';
    echo '<form id="drmx5-relay" method="post" action="">';
    foreach ($allowed as $field => $maximum) {
        if (!isset($_POST[$field]) || !is_scalar($_POST[$field])) {
            continue;
        }
        $value = substr((string) $_POST[$field], 0, $maximum);
        echo '<input type="hidden" name="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') .
            '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    }
    echo '<input type="hidden" name="_drmx5_same_site_relay" value="1">';
    echo '<noscript><button type="submit">Continue</button></noscript></form>';
    echo '<script>document.getElementById("drmx5-relay").submit();</script>';
    echo '</body></html>';
    exit;
}

$directory = __DIR__;
$wpload = '';
for ($level = 0; $level < 8; $level++) {
    $candidate = $directory . DIRECTORY_SEPARATOR . 'wp-load.php';
    if (is_file($candidate)) {
        $wpload = $candidate;
        break;
    }
    $parent = dirname($directory);
    if ($parent === $directory) {
        break;
    }
    $directory = $parent;
}

if ($wpload === '') {
    http_response_code(500);
    exit('WordPress could not be loaded.');
}

require_once $wpload;

if (!class_exists('DRMX5_LP_License_Controller')) {
    wp_die(
        esc_html__('The DRM-X 5.0 LearnPress integration plugin is not active.', 'drmx5-learnpress'),
        esc_html__('Unable to obtain DRM-X license', 'drmx5-learnpress'),
        array('response' => 503)
    );
}

DRMX5_LP_License_Controller::handle();

