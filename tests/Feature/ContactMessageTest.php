<?php

namespace Tests\Feature;

use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_returns_tracking_code(): void
    {
        Mail::fake();
        $res = $this->postJson('/api/v1/contact', $this->withAuthCaptcha([
            'name' => 'علی',
            'mobile' => '09123456789',
            'email' => 'ali@example.com',
            'subject' => 'support',
            'message' => 'سلام، نیاز به راهنمایی دارم.',
        ]));

        $res->assertOk()
            ->assertJsonPath('success', true);

        $code = $res->json('data.tracking_code');
        $this->assertNotEmpty($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'ali@example.com',
            'mobile' => '09123456789',
            'tracking_code' => $code,
        ]);
    }

    public function test_admin_can_reply_to_contact_message(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $msg = ContactMessage::query()->create([
            'tracking_code' => 'JA-TESTCODE',
            'name' => 'علی',
            'email' => 'ali@example.com',
            'subject' => 'support',
            'message' => 'متن پیام',
            'status' => 'open',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/contact-messages/{$msg->id}/reply", [
            'reply' => 'پاسخ مدیر',
        ])
            ->assertOk();

        $this->assertDatabaseHas('contact_messages', [
            'id' => $msg->id,
            'status' => 'replied',
            'reply' => 'پاسخ مدیر',
        ]);

        Mail::assertSent(ContactReplyMail::class, function (ContactReplyMail $mail) {
            return $mail->hasTo('ali@example.com')
                && $mail->replyText === 'پاسخ مدیر';
        });
    }
}
