<?php

namespace App\Services\Education;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserLessonBookmark;

class LessonBookmarkService
{
    public function __construct(private readonly EducationAccessService $access) {}

    public function save(User $user, Lesson $lesson): array
    {
        $lesson->loadMissing('module.course');
        $this->access->assertLessonAccess($user, $lesson);

        UserLessonBookmark::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'lesson_id' => $lesson->getKey(),
        ]);

        return ['lesson_uuid' => $lesson->uuid, 'bookmarked' => true];
    }

    public function remove(User $user, Lesson $lesson): array
    {
        UserLessonBookmark::query()
            ->where('user_id', $user->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->delete();

        return ['lesson_uuid' => $lesson->uuid, 'bookmarked' => false];
    }
}
