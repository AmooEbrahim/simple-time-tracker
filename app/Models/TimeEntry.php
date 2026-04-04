<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'description',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'project_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getDurationAttribute(): CarbonInterval
    {
        return CarbonInterval::seconds($this->duration_seconds);
    }

    public function getFormattedDurationAttribute(): string
    {
        $interval = $this->duration;

        if ($interval->totalHours >= 1) {
            return sprintf('%dh %dm', (int) $interval->totalHours, $interval->minutes);
        }

        return sprintf('%dm', $interval->totalMinutes);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function stop(Carbon $endedAt): void
    {
        $this->ended_at = $endedAt;
        $this->duration_seconds = $this->started_at->diffInSeconds($endedAt);
        $this->save();
    }
}
