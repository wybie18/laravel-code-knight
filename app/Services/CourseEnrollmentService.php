<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseEnrollmentService
{
    /**
     * Generate a unique course code
     */
    public function generateCourseCode(): string
    {
        do {
            // Generate a 8-character alphanumeric code (e.g., ABC123XY)
            $code = strtoupper(Str::random(3) . rand(100, 999) . Str::random(2));
        } while (Course::where('course_code', $code)->exists());

        return $code;
    }

    /**
     * Enroll a user in a course
     */
    public function enrollUser(User $user, Course $course): CourseEnrollment
    {
        // Check if already enrolled
        $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existingEnrollment) {
            throw new \Exception('User is already enrolled in this course.');
        }

        return CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);
    }

    /**
     * Enroll user by course code
     */
    public function enrollByCourseCode(User $user, string $courseCode): CourseEnrollment
    {
        $course = Course::where('course_code', $courseCode)->first();

        if (!$course) {
            throw new \Exception('Invalid course code.');
        }

        // Check if course is private
        if ($course->visibility !== 'private') {
            throw new \Exception('This course does not require a course code for enrollment.');
        }

        // Check if course is published
        if (!$course->is_published) {
            throw new \Exception('This course is not available for enrollment.');
        }

        return $this->enrollUser($user, $course);
    }

    /**
     * Enroll multiple students by course creator/teacher
     */
    public function enrollStudentsByCourseCreator(Course $course, array $studentIds, User $creator): array
    {
        // Verify the creator owns the course
        if ($course->created_by !== $creator->id && !$creator->tokenCan('admin:*')) {
            throw new \Exception('You do not have permission to enroll students in this course.');
        }

        $enrolled = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($studentIds as $studentId) {
                $student = User::find($studentId);

                if (!$student) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'message' => 'Student not found.',
                    ];
                    continue;
                }

                // Check if student role
                if ($student->role->name !== 'student') {
                    $errors[] = [
                        'student_id' => $studentId,
                        'message' => 'User is not a student.',
                    ];
                    continue;
                }

                // Check if already enrolled
                $existingEnrollment = CourseEnrollment::where('user_id', $studentId)
                    ->where('course_id', $course->id)
                    ->first();

                if ($existingEnrollment) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'message' => 'Student is already enrolled.',
                    ];
                    continue;
                }

                // Enroll the student
                $enrollment = CourseEnrollment::create([
                    'user_id' => $studentId,
                    'course_id' => $course->id,
                    'enrolled_at' => now(),
                ]);

                $enrolled[] = [
                    'student_id' => $studentId,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'enrollment_id' => $enrollment->id,
                ];
            }

            DB::commit();

            return [
                'enrolled' => $enrolled,
                'errors' => $errors,
                'total_enrolled' => count($enrolled),
                'total_errors' => count($errors),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove a student from course (by creator)
     */
    public function removeStudent(Course $course, int $studentId, User $creator): bool
    {
        // Verify the creator owns the course
        if ($course->created_by !== $creator->id && !$creator->tokenCan('admin:*')) {
            throw new \Exception('You do not have permission to remove students from this course.');
        }

        $enrollment = CourseEnrollment::where('user_id', $studentId)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            throw new \Exception('Student is not enrolled in this course.');
        }

        return $enrollment->delete();
    }

    /**
     * Get enrolled students for a course
     */
    public function getEnrolledStudents(Course $course, User $requester)
    {
        // Verify the requester owns the course or is admin
        if ($course->created_by !== $requester->id && !$requester->tokenCan('admin:*')) {
            throw new \Exception('You do not have permission to view enrolled students.');
        }

        return CourseEnrollment::where('course_id', $course->id)
            ->with(['user' => function ($query) {
                $query->select('id', 'username', 'first_name', 'last_name', 'email', 'student_id');
            }])
            ->get()
            ->map(function ($enrollment) {
                return [
                    'enrollment_id' => $enrollment->id,
                    'student' => [
                        'id' => $enrollment->user->id,
                        'username' => $enrollment->user->username,
                        'first_name' => $enrollment->user->first_name,
                        'last_name' => $enrollment->user->last_name,
                        'email' => $enrollment->user->email,
                        'student_id' => $enrollment->user->student_id,
                    ],
                    'enrolled_at' => $enrollment->enrolled_at,
                ];
            });
    }

    /**
     * Regenerate course code
     */
    public function regenerateCourseCode(Course $course, User $requester): string
    {
        // Verify the requester owns the course or is admin
        if ($course->created_by !== $requester->id && !$requester->tokenCan('admin:*')) {
            throw new \Exception('You do not have permission to regenerate the course code.');
        }

        $newCode = $this->generateCourseCode();
        $course->update(['course_code' => $newCode]);

        return $newCode;
    }

    /**
     * Check if user can enroll in course
     */
    public function canEnroll(User $user, Course $course): array
    {
        $canEnroll = true;
        $reason = null;

        // Check if already enrolled
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        if ($isEnrolled) {
            return [
                'can_enroll' => false,
                'reason' => 'Already enrolled in this course.',
            ];
        }

        // Check if course is published
        if (!$course->is_published) {
            return [
                'can_enroll' => false,
                'reason' => 'Course is not published.',
            ];
        }

        // Check visibility
        if ($course->visibility === 'private') {
            return [
                'can_enroll' => false,
                'reason' => 'This is a private course. You need a course code to enroll.',
                'requires_code' => true,
            ];
        }

        return [
            'can_enroll' => true,
            'reason' => null,
        ];
    }

    /**
     * Unenroll a user from a course (self-unenroll)
     */
    public function unenrollUser(User $user, Course $course): bool
    {
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            throw new \Exception('You are not enrolled in this course.');
        }

        return $enrollment->delete();
    }

    /**
     * Check if user is enrolled in a course
     */
    public function isUserEnrolled(User $user, Course $course): bool
    {
        return CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();
    }
}
