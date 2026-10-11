@php
    // PART D — branding from settings (nothing hard-coded; falls back to config default).
    $brandName = \App\Models\Setting::value('business_name', config('pmas.default_business_name'));
    $brandPrimary = \App\Models\Setting::value('brand_primary_color', config('pmas.default_brand_primary_color'));
    $brandAccent = \App\Models\Setting::value('brand_accent_color', config('pmas.default_brand_accent_color'));
    $brandLogo = \App\Models\Setting::value('logo_path');
@endphp
<!doctype html>
<html lang="en" style="@include('partials.brand-vars')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | {{ $brandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-shell { background: #edf4f3; min-height: 100vh; overflow: hidden; }
        .login-shape { position: absolute; pointer-events: none; }
        .login-shape-a { width: 30rem; height: 30rem; left: -12rem; top: -10rem; border-radius: 48% 52% 62% 38%; background: #bce7de; transform: rotate(24deg); }
        .login-shape-b { width: 21rem; height: 21rem; right: -7rem; bottom: -5rem; border-radius: 50%; background: #f6d49a; opacity: .8; }
        .login-shape-c { width: 12rem; height: 12rem; right: 17%; top: 10%; border: 1px solid rgba(15,159,143,.2); transform: rotate(45deg); }
        .login-glass { background: rgba(255,255,255,.64); border: 1px solid rgba(255,255,255,.82); box-shadow: 0 24px 70px rgba(26,65,70,.14); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
        .login-fade { animation: login-rise .65s ease both; }
        @keyframes login-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .login-fade { animation: none; } }
    </style>
</head>
<body class="login-shell text-[#182230]">
    <div class="relative isolate flex min-h-screen items-center justify-center px-5 py-8 sm:px-8">
        <div class="login-shape login-shape-a"></div><div class="login-shape login-shape-b"></div><div class="login-shape login-shape-c hidden sm:block"></div>
        <main class="relative grid w-full max-w-5xl overflow-hidden rounded-3xl login-glass lg:grid-cols-[1fr_1.05fr]">
            <section class="hidden bg-[#10253b] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
                <div><div class="flex items-center gap-3">@if($brandLogo)<img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }} logo" class="h-10 w-10 object-contain rounded-xl bg-white p-1">@else<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--brand-primary)] text-sm font-bold">Rx</span>@endif<span class="text-lg font-bold tracking-tight">{{ $brandName }}</span></div><p class="mt-20 max-w-sm text-4xl font-bold leading-tight tracking-tight">Everything your pharmacy needs, in one calm place.</p><p class="mt-5 max-w-sm text-sm leading-6 text-white/60">Manage medicines, retail products, inventory, sales, and branches from one local workspace.</p></div>
                <div class="flex items-center gap-3 text-xs text-white/50"><span class="h-2 w-2 rounded-full bg-[#72d4c5]"></span>Offline pharmacy operations</div>
            </section>
            <section class="login-fade bg-white/72 p-7 sm:p-10 lg:p-14">
                <div class="mb-10 lg:hidden"><div class="flex items-center gap-3">@if($brandLogo)<img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }} logo" class="h-10 w-10 object-contain rounded-xl bg-white p-1">@else<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#10253b] text-sm font-bold text-[#72d4c5]">Rx</span>@endif<span class="text-lg font-bold tracking-tight">{{ $brandName }}</span></div></div>
                <div class="mb-8"><p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--brand-primary)]">Welcome back</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-[#182230]">Sign in to {{ $brandName }}</h1><p class="mt-2 text-sm leading-6 text-[#6b7a93]">Use your staff account to continue managing the pharmacy.</p></div>
                @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                <form action="{{ route('login.submit') }}" method="POST" class="space-y-5">
                    @csrf
                    <div><label for="email" class="mb-2 block text-sm font-semibold text-[#182230]">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="you@example.com" class="w-full rounded-xl border border-[#d8e1e6] px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[var(--brand-primary)]/10" required autofocus></div>
                    <div><div class="mb-2 flex items-center justify-between"><label for="password" class="block text-sm font-semibold text-[#182230]">Password</label><span class="text-xs text-[#9aa6b6]">Staff access</span></div><input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" class="w-full rounded-xl border border-[#d8e1e6] px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-4 focus:ring-[var(--brand-primary)]/10" required></div>
                    <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-xl bg-[#10253b] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#10253b]/15 hover:-translate-y-0.5 hover:bg-[#183753]">Sign in <span class="text-[#72d4c5]">→</span></button>
                </form>
                <p class="mt-8 border-t border-[#edf1f3] pt-5 text-center text-xs leading-5 text-[#8a98a8]">{{ $brandName }} runs locally on this device. Your pharmacy data stays in your local system.</p>
            </section>
        </main>
    </div>
</body>
</html>
