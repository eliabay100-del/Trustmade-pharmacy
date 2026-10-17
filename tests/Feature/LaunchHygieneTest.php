<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Launch hygiene: the PWA manifest is generated (not a static file with Blade
 * syntax baked in), unknown URLs show a branded 404 rather than Laravel's
 * default page, and crawlers are kept out of the private app.
 */
class LaunchHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_generated_with_the_brand_name(): void
    {
        Setting::put('business_name', 'City Care Pharmacy');

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('Content-Type'));

        $manifest = $response->json();
        $this->assertSame('City Care Pharmacy', $manifest['name']);
        $this->assertSame('/favicon.ico', $manifest['icons'][0]['src']);
    }

    public function test_manifest_never_leaks_blade_syntax(): void
    {
        // The old public/manifest.json contained "{{ business_name | default(...) }}"
        // which a browser read literally, because static files are never compiled.
        $body = $this->get('/manifest.webmanifest')->getContent();

        $this->assertStringNotContainsString('{{', $body);
        $this->assertStringNotContainsString('default(', $body);
    }

    public function test_unknown_url_shows_the_branded_404_page(): void
    {
        // Tests run with the local .env, which has APP_DEBUG=true; force the
        // production rendering path so the branded view is exercised.
        config(['app.debug' => false]);

        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('We couldn’t find that page');
    }

    public function test_robots_txt_keeps_crawlers_out(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /', $robots);
    }
}
