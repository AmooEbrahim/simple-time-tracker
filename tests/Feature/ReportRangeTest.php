<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_week_start_uses_user_setting(): void
    {
        $user = User::factory()->create(['week_start' => 0]);
        $this->actingAs($user);

        $service = app(TimeTrackingService::class);
        $start = $service->getWeekStart(Carbon::parse('2026-10-24 12:00:00', 'Asia/Tehran'));

        $this->assertSame('2026-10-18', $start->format('Y-m-d'));

        auth()->logout();

        $default = $service->getWeekStart(Carbon::parse('2026-10-24 12:00:00', 'Asia/Tehran'));

        $this->assertSame('2026-10-24', $default->format('Y-m-d'));
    }

    public function test_dashboard_grouping_uses_user_week_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00:00', 'Asia/Tehran'));

        $user = User::factory()->create(['week_start' => 0]);
        $this->actingAs($user);

        TimeEntry::create([
            'user_id' => $user->id,
            'description' => 'Work',
            'started_at' => Carbon::parse('2026-10-24 09:00:00', 'Asia/Tehran')->timezone('UTC'),
            'ended_at' => Carbon::parse('2026-10-24 10:00:00', 'Asia/Tehran')->timezone('UTC'),
            'duration_seconds' => 3600,
        ]);

        $service = app(TimeTrackingService::class);
        [$groupedDays] = $service->getEntriesPaginated(null, null);

        $this->assertSame('2026-10-24', $groupedDays[0]['date']);
        $this->assertSame('2026-10-18', $groupedDays[0]['week_start']);
        $this->assertSame('2026-10-24', $groupedDays[0]['week_end']);
    }

    public function test_jalali_month_range_uses_calendar_bounds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00:00', 'Asia/Tehran'));

        $user = User::factory()->create(['calendar' => 'jalali']);

        $this->actingAs($user)
            ->get('/reports?range=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('range', 'month')
                ->where('startDate', '2026-10-23')
                ->where('endDate', '2026-11-21')
            );
    }

    public function test_explicit_iso_dates_are_honored(): void
    {
        $user = User::factory()->create(['calendar' => 'jalali']);

        $this->actingAs($user)
            ->get('/reports?range=month&start=2026-03-01&end=2026-03-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('startDate', '2026-03-01')
                ->where('endDate', '2026-03-31')
            );
    }

    public function test_jalali_looking_custom_dates_fall_back_to_defaults(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00:00', 'Asia/Tehran'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/reports?range=custom&start=1405-08-02&end=1405-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('startDate', '2026-09-24')
                ->where('endDate', '2026-10-24')
            );
    }
}
