<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'calendar',
    'week_start',
    'weekend_days',
    'show_holidays',
    'holiday_country',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'weekend_days' => 'array',
            'week_start' => 'integer',
            'show_holidays' => 'boolean',
        ];
    }

    /**
     * @return array{
     *     calendar: string,
     *     week_start: int,
     *     weekend_days: array<int, int>,
     *     show_holidays: bool,
     *     holiday_country: string
     * }
     */
    public static function defaultCalendarSettings(): array
    {
        return [
            'calendar' => 'jalali',
            'week_start' => 6,
            'weekend_days' => [4, 5],
            'show_holidays' => true,
            'holiday_country' => 'IR',
        ];
    }

    /**
     * @return array{
     *     calendar: string,
     *     week_start: int,
     *     weekend_days: array<int, int>,
     *     show_holidays: bool,
     *     holiday_country: string
     * }
     */
    public function calendarSettings(): array
    {
        $defaults = static::defaultCalendarSettings();

        $weekendDays = $this->weekend_days;
        if (! is_array($weekendDays)) {
            $weekendDays = $defaults['weekend_days'];
        }
        $weekendDays = array_values(array_unique(array_filter(
            array_map('intval', $weekendDays),
            fn (int $day) => $day >= 0 && $day <= 6
        )));

        $weekStart = $this->week_start ?? $defaults['week_start'];
        if (! is_int($weekStart) || $weekStart < 0 || $weekStart > 6) {
            $weekStart = $defaults['week_start'];
        }

        return [
            'calendar' => $this->calendar ?: $defaults['calendar'],
            'week_start' => $weekStart,
            'weekend_days' => $weekendDays,
            'show_holidays' => $this->show_holidays ?? $defaults['show_holidays'],
            'holiday_country' => $this->holiday_country ?: $defaults['holiday_country'],
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
