<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_email_subscription(): void
    {
        $res = $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => 'User@Example.com',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.already_subscribed', false);

        $this->assertDatabaseHas('newsletter_subscriptions', [
            'contact_type' => 'email',
            'contact_value' => 'user@example.com',
        ]);
    }

    public function test_valid_mobile_subscription(): void
    {
        $res = $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => '09123456789',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('newsletter_subscriptions', [
            'contact_type' => 'mobile',
            'contact_value' => '09123456789',
        ]);
    }

    public function test_invalid_contact_returns_422(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => 'not-valid',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_empty_contact_returns_422(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => '',
        ])
            ->assertStatus(422);
    }

    public function test_duplicate_subscription_is_idempotent(): void
    {
        NewsletterSubscription::query()->create([
            'contact_type' => 'email',
            'contact_value' => 'dup@example.com',
            'contact_hash' => hash('sha256', 'email:dup@example.com'),
        ]);

        $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => 'dup@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.already_subscribed', true);

        $this->assertSame(
            1,
            NewsletterSubscription::query()->where('contact_value', 'dup@example.com')->count()
        );
    }

    public function test_rate_limiting(): void
    {
        RateLimiter::clear('newsletter');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/newsletter/subscribe', [
                'contact' => "user{$i}@example.com",
            ])->assertOk();
        }

        $this->postJson('/api/v1/newsletter/subscribe', [
            'contact' => 'another@example.com',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
