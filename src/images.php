<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** A problem with an image the user supplied. The message is safe to show. */
class ImageException extends RuntimeException
{
}

/* -------------------------------------------------------------------------
 * Secure file upload
 * ---------------------------------------------------------------------- */

/**
 * Validate an uploaded file from $_FILES and store it.
 *
 * @return string web path of the stored image, e.g. "uploads/3f9c…a1.png"
 */
function store_uploaded_image(mixed $file): string
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        throw new ImageException('Invalid upload.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new ImageException('Choose an image file to upload.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new ImageException('That image is larger than the 2 MB limit.');
        default:
            throw new ImageException('The upload failed. Please try again.');
    }

    // Only accept files PHP itself received in this request.
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new ImageException('Invalid upload.');
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        throw new ImageException('That image is larger than the 2 MB limit.');
    }

    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) {
        throw new ImageException('The upload could not be read.');
    }

    // The browser-supplied name and MIME type ($file['name'], $file['type'])
    // are attacker-controlled and are deliberately never used.
    return store_image_bytes($bytes);
}

/**
 * Validate raw image bytes and write them to the uploads folder under a new
 * random name. Shared by uploads and URL imports.
 */
function store_image_bytes(string $bytes): string
{
    if ($bytes === '' || strlen($bytes) > MAX_IMAGE_BYTES) {
        throw new ImageException('Images must be between 1 byte and 2 MB.');
    }

    // 1. Sniff the real type from the content, not the name.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
        throw new ImageException('Only JPEG, PNG, GIF and WebP images are allowed.');
    }

    // 2. Make sure it actually parses as an image of that same type.
    $info = @getimagesizefromstring($bytes);
    if ($info === false || ($info['mime'] ?? '') !== $mime) {
        throw new ImageException('That file is not a valid image.');
    }
    [$width, $height] = $info;
    if ($width < 1 || $height < 1 || $width > MAX_IMAGE_DIMENSION || $height > MAX_IMAGE_DIMENSION) {
        throw new ImageException('Images must be at most ' . MAX_IMAGE_DIMENSION . ' pixels on each side.');
    }

    // 3. Re-encode when GD is available. This rebuilds the image from its
    //    pixels, dropping anything appended or hidden in metadata (polyglot
    //    payloads, EXIF location data). GIFs are kept as-is to preserve
    //    animation; they have already passed both checks above.
    $bytes = reencode_image($bytes, $mime);

    // 4. Server-chosen random name; the extension comes from our allow-list.
    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_IMAGE_TYPES[$mime];

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new ImageException('The server could not save the image.');
    }
    $target = UPLOAD_DIR . '/' . $name;
    if (file_put_contents($target, $bytes, LOCK_EX) === false) {
        throw new ImageException('The server could not save the image.');
    }
    chmod($target, 0644);   // readable, never executable

    return 'uploads/' . $name;
}

function reencode_image(string $bytes, string $mime): string
{
    $encoders = ['image/jpeg' => 'imagejpeg', 'image/png' => 'imagepng', 'image/webp' => 'imagewebp'];
    if (!isset($encoders[$mime]) || !function_exists('imagecreatefromstring') || !function_exists($encoders[$mime])) {
        return $bytes;
    }

    $img = @imagecreatefromstring($bytes);
    if ($img === false) {
        throw new ImageException('That file is not a valid image.');
    }
    if ($mime !== 'image/jpeg') {
        imagesavealpha($img, true);
    }

    ob_start();
    $ok = $mime === 'image/png' ? imagepng($img) : $encoders[$mime]($img, null, 90);
    $clean = ob_get_clean();

    if (!$ok || $clean === '' || $clean === false) {
        throw new ImageException('The server could not process that image.');
    }
    return $clean;
}

/** Remove a stored upload. Only ever touches files inside UPLOAD_DIR. */
function delete_stored_image(string $webPath): void
{
    if (!str_starts_with($webPath, 'uploads/')) {
        return;   // templates are shared and never deleted
    }
    $name = basename($webPath);
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $name)) {
        return;
    }
    $path = UPLOAD_DIR . '/' . $name;
    if (is_file($path)) {
        unlink($path);
    }
}

/* -------------------------------------------------------------------------
 * Import from URL (SSRF defences)
 * ---------------------------------------------------------------------- */

/**
 * Download an image from a public http(s) URL and store it.
 *
 * Defences:
 *  - only http/https, only the default ports, no credentials in the URL
 *  - the host is resolved once, and EVERY address must be public
 *    (no loopback, private, link-local/cloud-metadata, reserved ranges)
 *  - the connection is pinned to the checked address (no DNS rebinding)
 *  - redirects are not followed; size and time are capped
 *  - the response must pass the same image validation as an upload
 */
function import_image_from_url(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new ImageException('Importing from a URL needs the PHP curl extension. Upload the file instead.');
    }

    if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new ImageException('Enter a valid http:// or https:// image URL.');
    }

    $parts  = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    $host   = $parts['host'] ?? '';

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new ImageException('Only http:// and https:// URLs are allowed.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new ImageException('URLs containing a username or password are not allowed.');
    }

    $defaultPort = $scheme === 'https' ? 443 : 80;
    $port = $parts['port'] ?? $defaultPort;
    if ($port !== $defaultPort) {
        throw new ImageException('Only standard web ports are allowed.');
    }

    $ip = resolve_public_ip($host);

    $received = '';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . $ip],   // pin the vetted address
        CURLOPT_PROXY          => '',      // ignore *_proxy env vars so the pin holds
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPGET        => true,
        CURLOPT_USERAGENT      => APP_NAME . ' image importer',
        CURLOPT_HTTPHEADER     => ['Accept: image/*'],
        CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use (&$received): int {
            $received .= $chunk;
            // Returning a different length aborts the transfer.
            return strlen($received) > MAX_IMAGE_BYTES ? 0 : strlen($chunk);
        },
    ]);
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
    } else {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $ok     = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (strlen($received) > MAX_IMAGE_BYTES) {
        throw new ImageException('That image is larger than the 2 MB limit.');
    }
    if ($ok === false || $status !== 200) {
        // Deliberately vague: do not echo back what the server said.
        throw new ImageException('The image could not be downloaded from that URL.');
    }

    return store_image_bytes($received);
}

/**
 * Resolve a host name and return one address to connect to, but only if every
 * address it resolves to is a public, routable IP.
 */
function resolve_public_ip(string $host): string
{
    $host = trim($host, '[]');   // IPv6 literals arrive as [::1]

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $r) {
            if (isset($r['ip'])) {
                $ips[] = $r['ip'];
            } elseif (isset($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
        if (!$ips) {
            $ips = @gethostbynamel($host) ?: [];
        }
    }

    if (!$ips) {
        throw new ImageException('That host name could not be found.');
    }
    foreach ($ips as $ip) {
        if (!is_public_ip($ip)) {
            throw new ImageException('That URL points to a private or internal address, which is not allowed.');
        }
    }

    $ip = $ips[0];
    return str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
}

function is_public_ip(string $ip): bool
{
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (defined('FILTER_FLAG_GLOBAL_RANGE')) {   // PHP 8.2+
        $flags |= FILTER_FLAG_GLOBAL_RANGE;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
        return false;
    }

    // Belt and braces for ranges older PHP versions do not flag.
    $blocked = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15', '224.0.0.0/3',
    ];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        foreach ($blocked as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }
    } else {
        $lower = strtolower($ip);
        // loopback, unspecified, unique-local, link-local, IPv4-mapped
        if ($lower === '::1' || $lower === '::' || preg_match('/^(fc|fd|fe8|fe9|fea|feb|::ffff:)/', $lower)) {
            return false;
        }
    }
    return true;
}
