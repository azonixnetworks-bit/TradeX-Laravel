<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Entitlements\EducationPremiumEntitlementService;

class EducationAccessService
{
    public function __construct(
        private readonly EducationPremiumEntitlementService
            $educationPremium
    ) {
    }

    public function canAccessCourse(
        User $user,
        Course $course
    ): bool {
        if ($course->isFree()) {
            return true;
        }

        return $user->isActive()
            && ! $user->trashed()
            && $this->educationPremium
                ->hasActive($user);
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

    public function canAccessAssignmentsForLesson(
        User $user,
        Lesson $lesson
    ): bool {
        $course =
            $lesson->module
                ?->course;

        if (! $course instanceof Course) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Assignment premium policy
        |--------------------------------------------------------------------------
        |
        | A preview lesson may be opened by a FREE user, but assignments are
        | a premium entitlement. Therefore preview status never bypasses the
        | course entitlement for assignment/question/material access.
        |
        */
        return $this->canAccessCourse(
            $user,
            $course
        );
    }

    public function assertAssignmentLessonAccess(
        User $user,
        Lesson $lesson
    ): void {
        if (! $this->canAccessAssignmentsForLesson(
            $user,
            $lesson
        )) {
            throw new AuthApiException(
                403,
                'EDUCATION_ASSIGNMENT_PREMIUM_REQUIRED',
                'Premium education access is required for assignments in this lesson.'
            );
        }
    }

    public function assertAssignmentAccess(
        User $user,
        Assignment $assignment
    ): void {
        $assignment->loadMissing(
            'lesson.module.course'
        );

        $lesson =
            $assignment->lesson;

        if (! $lesson instanceof Lesson) {
            throw new AuthApiException(
                404,
                'ASSIGNMENT_NOT_AVAILABLE',
                'This assignment is not available.'
            );
        }

        $this->assertAssignmentLessonAccess(
            $user,
            $lesson
        );
    }
}
