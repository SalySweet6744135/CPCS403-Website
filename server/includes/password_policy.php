<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/password_policy.php
 * Purpose: Password policy — shared validation rules for registration
 */
const SHIPSMART_PASSWORD_MIN_LEN = 5;
const SHIPSMART_PASSWORD_MAX_LEN = 128;

/** Human-readable summary for forms and API errors. */
function shipsmart_password_rules_summary(): string
{
    return sprintf(
        'At least %d characters, including uppercase, lowercase, a number, and a special character (!@#$%%^&* etc.).',
        SHIPSMART_PASSWORD_MIN_LEN
    );
}

/**
 * @return string[] List of validation error messages (empty = valid).
 */
function shipsmart_password_errors(string $password): array
{
    $errors = [];

    $len = strlen($password);
    if ($len < SHIPSMART_PASSWORD_MIN_LEN) {
        $errors[] = sprintf('Password must be at least %d characters.', SHIPSMART_PASSWORD_MIN_LEN);
    }
    if ($len > SHIPSMART_PASSWORD_MAX_LEN) {
        $errors[] = sprintf('Password must not exceed %d characters.', SHIPSMART_PASSWORD_MAX_LEN);
    }
    if (preg_match('/\s/', $password)) {
        $errors[] = 'Password must not contain spaces.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter (A–Z).';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter (a–z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number (0–9).';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?\/\\\\~`"\']/', $password)) {
        $errors[] = 'Password must include at least one special character (e.g. ! @ # $ %).';
    }

    $lower = strtolower($password);
    $blocked = ['password', 'password123', '123456789', 'qwerty123', 'admin123', 'letmein'];
    foreach ($blocked as $bad) {
        if ($lower === $bad || str_contains($lower, $bad)) {
            $errors[] = 'This password is too common. Choose a more unique password.';
            break;
        }
    }

    return $errors;
}

function shipsmart_password_is_valid(string $password): bool
{
    return shipsmart_password_errors($password) === [];
}
