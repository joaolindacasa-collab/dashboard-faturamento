<?php

namespace Tests\Feature;

use App\Mail\SyncStoppedAlert;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TinySyncAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tiny.alert.enabled' => true,
            'tiny.alert.email' => 'alerta@example.com',
            'tiny.alert.max_age_hours' => 2,
            'tiny.alert.cooldown_hours' => 6,
        ]);
        Cache::flush();
    }

    private function syncOk(string $finishedAt): SyncLog
    {
        return SyncLog::create([
            'mode' => 'incremental',
            'status' => 'ok',
            'orders_seen' => 1,
            'orders_upserted' => 1,
            'started_at' => $finishedAt,
            'finished_at' => $finishedAt,
        ]);
    }

    public function test_nao_envia_quando_sync_ok_recente(): void
    {
        Mail::fake();
        $this->syncOk(now()->subMinutes(10));

        $this->artisan('tiny:sync-alert')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_envia_quando_sync_ok_e_antiga(): void
    {
        Mail::fake();
        $this->syncOk(now()->subHours(5));

        $this->artisan('tiny:sync-alert')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_envia_quando_nunca_houve_sync_ok(): void
    {
        Mail::fake();

        $this->artisan('tiny:sync-alert')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_cooldown_impede_reenvio(): void
    {
        Mail::fake();
        $this->syncOk(now()->subHours(5));

        $this->artisan('tiny:sync-alert')->assertSuccessful(); // envia
        $this->artisan('tiny:sync-alert')->assertSuccessful(); // cooldown

        Mail::assertSentCount(1);
    }

    public function test_force_ignora_cooldown(): void
    {
        Mail::fake();
        $this->syncOk(now()->subHours(5));

        $this->artisan('tiny:sync-alert')->assertSuccessful();
        $this->artisan('tiny:sync-alert --force')->assertSuccessful();

        Mail::assertSentCount(2);
    }

    public function test_desabilitado_nao_envia(): void
    {
        Mail::fake();
        config(['tiny.alert.enabled' => false]);

        $this->artisan('tiny:sync-alert')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_cai_nos_admins_sem_email_configurado(): void
    {
        Mail::fake();
        config(['tiny.alert.email' => null]);
        User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']);
        User::factory()->create(['is_admin' => false, 'email' => 'comum@example.com']);

        $this->artisan('tiny:sync-alert')->assertSuccessful();

        Mail::assertSent(SyncStoppedAlert::class, function ($mail) {
            return $mail->hasTo('admin@example.com') && ! $mail->hasTo('comum@example.com');
        });
    }
}
