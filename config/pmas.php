<?php

/**
 * PART D — Branding defaults. These are only fallbacks for a fresh install;
 * the real values live in the settings table (Settings > Business info) so
 * any pharmacy can be branded without touching code. "PMAS" is the generic
 * product name, not a client name.
 */
return [
    'default_business_name' => env('BRANDING_BUSINESS_NAME', 'PMAS'),
];
