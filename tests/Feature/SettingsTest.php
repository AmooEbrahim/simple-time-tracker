<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_settings_page_is_displayed_with_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('settings', User::defaultCalendarSettings())
            );
    }

    public function test_user_can_update_settings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings')
            ->patch('/settings', [
                'calendar' => 'hijri',
                'week_start' => 0,
                'weekend_days' => [5],
                'show_holidays' => false,
                'holiday_country' => 'IR',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Settings saved.');

        $user->refresh();

        $this->assertSame('hijri', $user->calendar);
        $this->assertSame(0, $user->week_start);
        $this->assertSame([5], $user->weekend_days);
        $this->assertFalse($user->show_holidays);
        $this->assertSame('IR', $user->holiday_country);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.calendar', 'hijri')
                ->where('settings.week_start', 0)
                ->where('settings.weekend_days', [5])
                ->where('settings.show_holidays', false)
                ->where('settings.holiday_country', 'IR')
            );
    }

    public function test_weekend_days_can_be_empty(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from('/settings')
            ->patch('/settings', [
                'calendar' => 'jalali',
                'week_start' => 6,
                'weekend_days' => [],
                'show_holidays' => true,
                'holiday_country' => 'IR',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame([], $user->weekend_days);
        $this->assertSame([], $user->calendarSettings()['weekend_days']);
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $user = User::factory()->create();

        $valid = [
            'calendar' => 'jalali',
            'week_start' => 6,
            'weekend_days' => [4, 5],
            'show_holidays' => true,
            'holiday_country' => 'IR',
        ];

        $cases = [
            'calendar' => [['calendar' => 'mayan'], 'calendar'],
            'week_start' => [['week_start' => 7], 'week_start'],
            'weekend_days' => [['weekend_days' => [7]], 'weekend_days.0'],
            'duplicate weekend days' => [['weekend_days' => [4, 4]], 'weekend_days.1'],
            'holiday_country' => [['holiday_country' => 'US'], 'holiday_country'],
        ];

        foreach ($cases as $label => [$override, $errorKey]) {
            $response = $this
                ->actingAs($user)
                ->from('/settings')
                ->patch('/settings', array_merge($valid, $override));

            $response->assertSessionHasErrors($errorKey);
        }
    }

    public function test_fresh_user_has_default_settings(): void
    {
        $user = User::factory()->create();

        $user->refresh();

        $this->assertSame(User::defaultCalendarSettings(), $user->calendarSettings());
        $this->assertSame('jalali', $user->calendar);
        $this->assertSame(6, $user->week_start);
        $this->assertSame([4, 5], $user->weekend_days);
        $this->assertTrue($user->show_holidays);
        $this->assertSame('IR', $user->holiday_country);
    }
}
