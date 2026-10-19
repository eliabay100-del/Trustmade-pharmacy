<?php

return [

    /*
    |---------------------------------------------------------------------------
    | The pharmacy's public host name
    |---------------------------------------------------------------------------
    |
    | Registered on 18 October 2026 and not yet pointed at a host, so it is
    | kept in one place rather than typed into views. It drives documentation
    | and cache-busting only - Laravel reads the live host from each request,
    | and APP_URL is what generates absolute links.
    |
    | Nothing in the application depends on this value being correct, which is
    | deliberate: the install keeps working if the name changes, and nothing
    | silently breaks because the domain moved.
    |
    */

    'domain' => env('TRUSTMED_DOMAIN', 'trustmed.pharmacy.et'),

    /*
    |---------------------------------------------------------------------------
    | Trusted proxies
    |---------------------------------------------------------------------------
    |
    | Anything that terminates the connection in front of PHP - a reverse proxy,
    | a CDN, or the host's own front end. Without this, every request looks like
    | plain HTTP even when the browser used HTTPS, and the HTTPS redirect in
    | SecurityHeaders loops forever.
    |
    | Set to "*" ONLY when nothing untrusted can reach PHP directly. On shared
    | hosting the safe answer is the single proxy IP; find it with:
    |
    |   php artisan pmas:preflight --json
    |
    | which echoes the address it saw in REMOTE_ADDR.
    |
    | Read here rather than from bootstrap/app.php so the value is available
    | after the configuration is cached, where env() returns null.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |---------------------------------------------------------------------------
    | Fingerprint of the APP_KEY that leaked into git
    |---------------------------------------------------------------------------
    |
    | Before .env was untracked the whole file - including this key - was
    | committed, so it is public: anyone who can read the repository can decrypt
    | session cookies and forge signed URLs against it.
    |
    | This is the SHA-256 of that key rather than the key itself, so preflight
    | can recognise it without writing the secret into a second file. The
    | command only compares a hash against it; the value recovers nothing.
    |
    | Kept here rather than inside the command so a test can substitute its own
    | fingerprint. A checker that cannot be tested is a checker nobody trusts.
    |
    */

    'leaked_key_fingerprint' => env(
        'LEAKED_KEY_FINGERPRINT',
        'e8b9d7c5f3164b273558a272401e8728b57b0fd3f3c12ea7b977cf5aaca5f939'
    ),

];
