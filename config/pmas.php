<?php

/**
 * PART D — Branding defaults. These are only fallbacks for a fresh install;
 * the real values live in the settings table (Settings > Business info) so
 * any pharmacy can be branded without touching code. "PMAS" is the generic
 * product name, not a client name.
 */
return [
    'default_business_name' => env('BRANDING_BUSINESS_NAME', 'PMAS'),

    // Fallback palette for a fresh install; the owner can change both on the
    // Business settings page, which feeds the --brand-* CSS variables.
    'default_brand_primary_color' => env('BRANDING_PRIMARY_COLOR', '#12A594'),
    'default_brand_accent_color' => env('BRANDING_ACCENT_COLOR', '#101B2D'),
];
