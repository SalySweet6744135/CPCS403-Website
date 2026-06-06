<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/verification_token.php
 * Purpose: Signed registration tokens — hold pending signup data until email is verified (no DB row until then)
 */

/** Token lifetime in seconds (24 hours). */
define('SHHIPSMART_REG_TOKEN_TTL', 86400);

function shipsmart_registration_secret(): string
{
    return 'shipsmart-cpcs403-registration-verify-v1';
}

function shipsmart_app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir    = dirname($script);
    if (str_ends_with($dir, '/api')) {
        $dir = dirname($dir);
    }
    $base = rtrim($dir, '/');
    if ($base === '/') {
        $base = '';
    }

    return $scheme . '://' . $host . $base;
}

/**
 * @param array{full_name:string,email:string,password_hash:string,exp:int} $payload
 */
function shipsmart_registration_token_create(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return '';
    }

    $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig  = hash_hmac('sha256', $body, shipsmart_registration_secret());

    return $body . '.' . $sig;
}

/**
 * @return array{full_name:string,email:string,password_hash:string,exp:int}|null
 */
function shipsmart_registration_token_parse(string $token): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$body, $sig] = $parts;
    $expected = hash_hmac('sha256', $body, shipsmart_registration_secret());
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $pad   = strlen($body) % 4;
    $b64   = $body . ($pad ? str_repeat('=', 4 - $pad) : '');
    $json  = base64_decode(strtr($b64, '-_', '+/'), true);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    foreach (['full_name', 'email', 'password_hash', 'exp'] as $key) {
        if (!isset($data[$key])) {
            return null;
        }
    }

    if (!is_int($data['exp']) && !ctype_digit((string) $data['exp'])) {
        return null;
    }

    $data['exp'] = (int) $data['exp'];
    if ($data['exp'] < time()) {
        return null;
    }

    return $data;
}

function shipsmart_registration_verify_url(string $token): string
{
    return shipsmart_app_base_url() . '/verify-email.php?token=' . rawurlencode($token);
}
