<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonProgress extends Model
{
    use HasFactory;

    protected $table = 'user_lesson_progresses';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'status',
        'last_video_id',
        'last_position_seconds',
        'started_at',
        'completed_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_position_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(
            Lesson::class
        );
    }

    public function lastVideo(): BelongsTo
    {
        return $this->belongsTo(
            LessonVideo::class,
            'last_video_id'
        );
    }

    public function isCompleted(): bool
    {
        return $this->status
            === self::STATUS_COMPLETED;
    }
}