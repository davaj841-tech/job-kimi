<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_slugs_are_created_and_public(): void
    {
        $this->getJson('/api/v1/pages/terms')
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms')
            ->assertJsonPath('data.is_published', true);

        $this->getJson('/api/v1/pages/privacy')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertOk();
        $this->getJson('/api/v1/pages/contact')->assertOk();
        $this->getJson('/api/v1/pages/refund')->assertOk();

        $this->assertSame(5, CmsPage::query()->whereIn('slug', ['terms', 'privacy', 'about', 'contact', 'refund'])->count());
    }

    public function test_existing_legal_page_is_not_overwritten(): void
    {
        CmsPage::query()->create([
            'slug' => 'about',
            'title' => 'درباره سفارشی',
            'content' => '<p>متن مدیر</p>',
            'is_published' => true,
        ]);

        $this->getJson('/api/v1/pages/about')
            ->assertOk()
            ->assertJsonPath('data.title', 'درباره سفارشی')
            ->assertJsonPath('data.content', '<p>متن مدیر</p>');
    }
}
