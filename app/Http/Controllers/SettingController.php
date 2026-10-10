<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * PART D — Branding / business settings. Owner/Admin only (routes enforce
 * the role). Everything about the business (name, logo, colors, contact
 * details, receipt text) lives here as key/value settings — nothing is
 * hard-coded, so another pharmacy can be branded by changing these
 * settings alone.
 */
class SettingController extends Controller
{
    /** Keys that may be written through this page, and their rules. */
    protected const TEXT_FIELDS = [
        'business_name' => 'required|string|max:100',
        'business_phone' => 'nullable|string|max:30',
        'business_address' => 'nullable|string|max:255',
        'receipt_header' => 'nullable|string|max:255',
        'receipt_footer' => 'nullable|string|max:255',
        // Part B — how many days ahead supplier payment reminders appear.
        'supplier_alert_days' => 'nullable|integer|min:1|max:90',
    ];

    public function edit()
    {
        $settings = [
            'business_name' => Setting::value('business_name', config('pmas.default_business_name')),
            'business_phone' => Setting::value('business_phone'),
            'business_address' => Setting::value('business_address'),
            'receipt_header' => Setting::value('receipt_header'),
            'receipt_footer' => Setting::value('receipt_footer'),
            'brand_primary_color' => Setting::value('brand_primary_color', '#12A594'),
            'brand_accent_color' => Setting::value('brand_accent_color', '#101B2D'),
            'logo_path' => Setting::value('logo_path'),
            'supplier_alert_days' => Setting::value('supplier_alert_days', '7'),
        ];

        return view('settings.branding', compact('settings'));
    }

    public function update(Request $request)
    {
        $hex = '#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})';

        $data = $request->validate([
            ...self::TEXT_FIELDS,
            'brand_primary_color' => ['required', 'string', 'regex:/^' . $hex . '$/'],
            'brand_accent_color' => ['required', 'string', 'regex:/^' . $hex . '$/'],
            // Logo: images only, max 2 MB, stored on the public disk.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => 'nullable|boolean',
        ], [
            'brand_primary_color.regex' => 'Please use a valid color code like #12A594.',
            'brand_accent_color.regex' => 'Please use a valid color code like #101B2D.',
            'logo.image' => 'The logo must be an image (JPG, PNG or WebP).',
            'logo.max' => 'The logo must be smaller than 2 MB.',
            'business_name.required' => 'Please enter the business name.',
        ]);

        // --- Text / color settings ---
        foreach (array_filter(self::TEXT_FIELDS, fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY) as $key => $rule) {
            Setting::put($key, $data[$key] ?? null);
        }
        Setting::put('brand_primary_color', $data['brand_primary_color']);
        Setting::put('brand_accent_color', $data['brand_accent_color']);

        // --- Logo upload (validated above; stored safely under an ASCII name) ---
        if ($request->hasFile('logo')) {
            $old = Setting::value('logo_path');
            $path = $request->file('logo')->store('branding', 'public');
            Setting::put('logo_path', $path);
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_logo')) {
            $old = Setting::value('logo_path');
            Setting::put('logo_path', null);
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        return back()->with('success', 'Settings saved! The whole system now uses your business name, logo and colors.');
    }
}
