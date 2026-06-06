<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/email_policy.php
 * Purpose: Email validation — reject disposable, fake, and malformed addresses at registration
 */

/** Domains commonly used for throwaway / fake signups. */
function shipsmart_blocked_email_domains(): array
{
    return [
        'example.com', 'example.org', 'example.net', 'test.com', 'localhost',
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com',
        'grr.la', 'tempmail.com', 'temp-mail.org', 'throwaway.email', 'yopmail.com',
        'fakeinbox.com', 'trashmail.com', '10minutemail.com', 'dispostable.com',
        'getnada.com', 'maildrop.cc', 'mintemail.com', 'emailondeck.com',
    ];
}

/**
 * Reject fake-looking domains such as 1.com or 999.net.
 */
function shipsmart_email_domain_error(string $domain): ?string
{
    $domain = strtolower(trim($domain));
    if ($domain === '' || !str_contains($domain, '.')) {
        return 'Email domain is invalid. Use a real provider like gmail.com or outlook.com.';
    }

    if (!preg_match('/[a-z]/', $domain)) {
        return 'Email domain must include letters (e.g. gmail.com, not 1.com).';
    }

    $labels = explode('.', $domain);
    $tld    = array_pop($labels);

    if ($tld === '' || !preg_match('/^[a-z]{2,}$/', $tld)) {
        return 'Email domain must end with a valid extension (e.g. .com, .edu).';
    }

    foreach ($labels as $label) {
        if ($label === '') {
            return 'Email domain is invalid.';
        }
        if (strlen($label) < 2) {
            return 'Email domain is invalid. Use a real provider like gmail.com.';
        }
        if (!preg_match('/[a-z]/', $label)) {
            return 'Email domain is invalid. Use a real provider like gmail.com.';
        }
        if (preg_match('/^\d+$/', $label)) {
            return 'Email domain cannot be numbers only (e.g. 1.com is not allowed).';
        }
    }

    return null;
}

/**
 * @return string|null Error message, or null if the email looks acceptable.
 */
function shipsmart_email_error(string $email): ?string
{
    $email = strtolower(trim($email));

    if ($email === '') {
        return 'Please enter your email address.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address (e.g. name@gmail.com).';
    }

    if (!preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/i', $email)) {
        return 'Email format is invalid. Use a real address like name@provider.com.';
    }

    [$local, $domain] = explode('@', $email, 2);

    if (strlen($local) < 2) {
        return 'The part before @ must be at least 2 characters.';
    }

    if (str_contains($local, '..') || str_starts_with($local, '.') || str_ends_with($local, '.')) {
        return 'Email address contains invalid characters.';
    }

    if (in_array($domain, shipsmart_blocked_email_domains(), true)) {
        return 'Disposable or test email addresses are not allowed. Use a real inbox.';
    }

    $blockedLocals = ['test', 'fake', 'noreply', 'no-reply', 'admin', 'user', 'demo'];
    if (in_array($local, $blockedLocals, true)) {
        return 'Please use your personal email address, not a generic placeholder.';
    }

    $domainError = shipsmart_email_domain_error($domain);
    if ($domainError !== null) {
        return $domainError;
    }

    return null;
}
