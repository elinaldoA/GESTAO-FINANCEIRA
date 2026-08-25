<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\FinancialAlertsDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FinancialAlertsDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_a_digest_only_to_users_with_pending_alerts(): void
    {
        Notification::fake();

        $userWithIssue = User::factory()->create();
        $account = $userWithIssue->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $userWithIssue->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Conta atrasada',
            'amount' => 50,
            'date' => now()->subDays(2),
            'is_paid' => false,
        ]);

        $userWithoutIssues = User::factory()->create();

        $this->artisan('finance:send-alerts')->assertExitCode(0);

        Notification::assertSentTo($userWithIssue, FinancialAlertsDigest::class);
        Notification::assertNotSentTo($userWithoutIssues, FinancialAlertsDigest::class);
    }

    public function test_digest_mail_lists_the_alert_messages(): void
    {
        $user = User::factory()->create();

        $notification = new FinancialAlertsDigest([
            ['severity' => 'error', 'message' => 'Você tem 1 transação pendente vencida.', 'url' => '#'],
        ]);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('transação pendente vencida', implode(' ', $mail->introLines));
    }
}
