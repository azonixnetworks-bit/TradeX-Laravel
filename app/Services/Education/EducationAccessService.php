<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

class EducationAccessService
{
    public function canAccessCourse(
        User $user,
        Course $course
    ): bool {
        if ($course->isFree()) {
            return true;
        }

        return $user->canAccessPremium();
    }

    public function canAccessLesson(
        User $user,
        Lesson $lesson
    ): bool {
        $course =
            $lesson->module
                ?->course;

        if (! $course instanceof Course) {
            return false;
        }

        if ($this->canAccessCourse(
            $user,
            $course
        )) {
            return true;
        }

        return $lesson->isPreview();
    }

    public function assertLessonAccess(
        User $user,
        Lesson $lesson
    ): void {
        if (! $this->canAccessLesson(
            $user,
            $lesson
        )) {
            throw new AuthApiException(
                403,
                'EDUCATION_PREMIUM_REQUIRED',
                'Premium education access is required for this lesson.'
            );
        }
    }
}
