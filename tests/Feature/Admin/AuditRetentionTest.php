<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Lottery;
use Tests\TestCase;

class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Lottery::determineResultNormally();

        parent::tearDown();
    }

    private function eventAgedDays(int $days): AuditEvent
    {
        $this->travelTo(now()->subDays($days));
        $event = AuditEvent::create(['action' => 'test.event']);
        $this->travelBack();

        return $event;
    }

    public function test_default_retention_is_730_days(): void
    {
        $this->assertSame(730, config('audit.retention_days'));
    }

    public function test_prune_command_deletes_only_expired_events(): void
    {
        $expired = $this->eventAgedDays(731);
        $kept = $this->eventAgedDays(729);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }

    public function test_retention_is_configurable(): void
    {
        config(['audit.retention_days' => 30]);
        $expired = $this->eventAgedDays(31);
        $kept = $this->eventAgedDays(29);

        app(AuditLogger::class)->pruneExpired();

        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }

    public function test_zero_disables_deletion(): void
    {
        config(['audit.retention_days' => 0]);
        $old = $this->eventAgedDays(5000);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertModelExists($old);
    }

    public function test_expired_events_are_pruned_without_cron_when_new_events_are_written(): void
    {
        $expired = $this->eventAgedDays(800);
        Lottery::alwaysWin();

        app(AuditLogger::class)->record('test.new');

        $this->assertModelMissing($expired);
        $this->assertDatabaseHas('audit_events', ['action' => 'test.new']);
    }

    public function test_laravel_model_prune_uses_the_same_rule(): void
    {
        $expired = $this->eventAgedDays(731);
        $kept = $this->eventAgedDays(1);

        $this->artisan('model:prune', ['--model' => [AuditEvent::class]])->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }
}
