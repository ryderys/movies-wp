<?php
/**
 * Signed media URL verifier for media.asiastarx.ir
 *
 * Expects nginx to pass:
 *   MEDIA_TOKEN  - token from /v/{token} or /d/{token}
 *   MEDIA_TYPE   - v|d (must match token typ)
 *
 * Config via environment or /etc/asiastarx-media.env.php returning:
 *   MEDIA_URL_SECRET, MEDIA_DATA_ROOT (default /data)
 *
 * On success: X-Accel-Redirect to /internal-media/{relative_path}
 * On failure: 403/404 JSON or plain text
 */

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

/**
 * @return array{secret:string,data_root:string}
 */
function media_load_config(): array {
	$secret    = getenv('MEDIA_URL_SECRET') ?: '';
	$data_root = getenv('MEDIA_DATA_ROOT') ?: '/data';

	$config_file = '/etc/asiastarx-media.env.php';
	if (is_readable($config_file)) {
		$cfg = include $config_file;
		if (is_array($cfg)) {
			if (!empty($cfg['MEDIA_URL_SECRET'])) {
				$secret = (string) $cfg['MEDIA_URL_SECRET'];
			}
			if (!empty($cfg['MEDIA_DATA_ROOT'])) {
				$data_root = (string) $cfg['MEDIA_DATA_ROOT'];
			}
		}
	}

	return array(
		'secret'    => $secret,
		'data_root' => rtrim($data_root, '/'),
	);
}

function media_b64url_decode(string $data): string|false {
	$remainder = strlen($data) % 4;
	if ($remainder > 0) {
		$data .= str_repeat('=', 4 - $remainder);
	}
	return base64_decode(strtr($data, '-_', '+/'), true);
}

function media_fail(int $code, string $message): void {
	http_response_code($code);
	header('Content-Type: text/plain; charset=UTF-8');
	echo $message;
	exit;
}

$config = media_load_config();
if ($config['secret'] === '') {
	media_fail(500, 'Media secret not configured');
}

$token = isset($_SERVER['MEDIA_TOKEN']) ? (string) $_SERVER['MEDIA_TOKEN'] : '';
$type  = isset($_SERVER['MEDIA_TYPE']) ? strtolower((string) $_SERVER['MEDIA_TYPE']) : '';

if ($token === '' && isset($_GET['token'])) {
	$token = (string) $_GET['token'];
}
if ($type === '' && isset($_GET['type'])) {
	$type = strtolower((string) $_GET['type']);
}

$token = rawurldecode($token);

if ($token === '' || !in_array($type, array('v', 'd'), true)) {
	media_fail(400, 'Bad request');
}

$parts = explode('.', $token, 2);
if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
	media_fail(403, 'Invalid token');
}

[$payload_b64, $sig_b64] = $parts;

$expected_sig = hash_hmac('sha256', $payload_b64, $config['secret'], true);
$given_sig    = media_b64url_decode($sig_b64);
if ($given_sig === false || !hash_equals($expected_sig, $given_sig)) {
	media_fail(403, 'Invalid signature');
}

$payload_json = media_b64url_decode($payload_b64);
if ($payload_json === false) {
	media_fail(403, 'Invalid payload');
}

$payload = json_decode($payload_json, true);
if (!is_array($payload)) {
	media_fail(403, 'Invalid payload json');
}

$exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
$typ = isset($payload['typ']) ? strtolower((string) $payload['typ']) : '';
$rel = isset($payload['p']) ? str_replace('\\', '/', (string) $payload['p']) : '';
$rel = ltrim($rel, '/');

if ($exp < time()) {
	media_fail(403, 'Token expired');
}
if ($typ !== $type) {
	media_fail(403, 'Token type mismatch');
}
if ($rel === '' || str_contains($rel, "\0")) {
	media_fail(403, 'Invalid path');
}

foreach (explode('/', $rel) as $segment) {
	if ($segment === '' || $segment === '.' || $segment === '..') {
		media_fail(403, 'Invalid path segment');
	}
}

$abs = $config['data_root'] . '/' . $rel;
$real_root = realpath($config['data_root']);
$real_file = realpath($abs);

if ($real_root === false || $real_file === false || !is_file($real_file)) {
	media_fail(404, 'Not found');
}

// Ensure resolved file stays under data root.
$root_prefix = rtrim(str_replace('\\', '/', $real_root), '/') . '/';
$file_norm   = str_replace('\\', '/', $real_file);
if (!str_starts_with($file_norm, $root_prefix) && $file_norm !== rtrim($root_prefix, '/')) {
	media_fail(403, 'Path escaped data root');
}

// Relative path for internal nginx location (no leading slash).
$accel = substr($file_norm, strlen(rtrim($root_prefix, '/')));
$accel = ltrim(str_replace('\\', '/', $accel), '/');

if ($type === 'd') {
	header('Content-Disposition: attachment; filename="' . rawurlencode(basename($real_file)) . '"');
}

// nginx internal redirect — see media.asiastarx.ir site config
header('X-Accel-Redirect: /internal-media/' . $accel);
header('Content-Type: '); // let nginx set from file
exit;
