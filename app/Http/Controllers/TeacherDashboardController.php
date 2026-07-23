<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\Submission;
use App\Models\Assignment;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        $teacherId = Auth::id();

        // 1. Calculate Real Stats
        $activeCourses = Course::where('teacher_id', $teacherId)->count();
        
        // FIX: Counts ONLY unique students, and completely ignores deleted 'ghost' accounts!
        $totalStudents = Enrollment::whereHas('course', function($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->whereHas('user') // Ensures the user actually exists in the database
            ->where('status', 'approved')
            ->distinct('user_id')
            ->count('user_id');

        $pendingSubmissions = Submission::whereHas('assignment.course', function($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })->whereNull('grade')->count();

        // 2. Actionable Grading Queue (Groups submissions by Assignment)
        $gradingQueue = Assignment::whereHas('course', function($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->withCount(['submissions as ungraded_count' => function($q) {
                $q->whereNull('grade');
            }])
            ->with('course:id,title')
            ->get()
            ->filter(function ($assignment) {
                return $assignment->ungraded_count > 0;
            })
            ->sortByDesc('ungraded_count')
            // REMOVED: ->take(5) so the frontend has ALL data for the "See All" modal
            ->values()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'course' => $assignment->course->title,
                    'course_id' => $assignment->course_id,
                    'ungraded_count' => $assignment->ungraded_count
                ];
            });

        // 3. Upcoming Deadlines
        $upcomingAssignments = Assignment::whereHas('course', function($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->where('due_date', '>=', now())
            ->orderBy('due_date', 'asc')
            ->with('course:id,title')
            // REMOVED: ->limit(4) so the frontend has ALL data for the "See All" modal
            ->get();

        // ==========================================
        // NEW: Broadcast, Health & Insights Logic
        // ==========================================

        // A. Courses for Quick Broadcast Dropdown
        $broadcastCourses = Course::where('teacher_id', $teacherId)
            ->where('is_published', true)
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        // B. Classroom Health Calculations
        $coursesWithHealth = Course::where('teacher_id', $teacherId)
            ->where('is_published', true)
            ->with(['assignments', 'enrollments' => function($q) {
                $q->where('status', 'approved')->with('user.submissions');
            }])->get();

        $classroomHealth = [];
        $totalFailingStudents = 0;
        $totalMissingTasks = 0;
        $mostStrugglingCourse = null;
        $highestFailingCount = 0;

        foreach ($coursesWithHealth as $course) {
            $failingCount = 0;
            $missingTaskCount = 0;
            $coursePoints = $course->assignments->sum('points');

            foreach ($course->enrollments as $enrollment) {
                if ($student = $enrollment->user) {
                    
                    // Metric 1: Failing Students (< 75%)
                    if ($coursePoints > 0) {
                        $earned = $student->submissions->whereIn('assignment_id', $course->assignments->pluck('id'))->sum('grade');
                        $avg = ($earned / $coursePoints) * 100;
                        if ($avg < 75) {
                            $failingCount++;
                        }
                    }

                    // Metric 2: Missing Past-Due Tasks
                    $pastDueAssignments = $course->assignments->filter(function($a) {
                        return $a->due_date && $a->due_date < now();
                    });

                    foreach($pastDueAssignments as $assignment) {
                        $isLate = false;
                        // Prevent flagging late enrollees as missing tasks
                        if (str_contains($assignment->description ?? '', '[RESTRICT_LATE_STUDENTS]')) {
                            $enrollDate = $enrollment->created_at ?? $enrollment->enrolled_at;
                            if ($enrollDate > $assignment->due_date) {
                                $isLate = true;
                            }
                        }

                        if (!$isLate) {
                            $hasSubmitted = $student->submissions->where('assignment_id', $assignment->id)->isNotEmpty();
                            if (!$hasSubmitted) {
                                $missingTaskCount++;
                            }
                        }
                    }
                }
            }

            // Only add course to health card if there are red flags
            if ($failingCount > 0 || $missingTaskCount > 0) {
                $classroomHealth[] = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'failing_count' => $failingCount,
                    'missing_tasks' => $missingTaskCount
                ];

                $totalFailingStudents += $failingCount;
                $totalMissingTasks += $missingTaskCount;

                if ($failingCount > $highestFailingCount) {
                    $highestFailingCount = $failingCount;
                    $mostStrugglingCourse = $course->title;
                }
            }
        }

        // C. AI Insights Smart Logic
        $aiInsight = "Your classrooms are looking healthy today. Keep up the great work!";
        if ($totalFailingStudents > 0 || $totalMissingTasks > 0) {
            $parts = [];
            if ($totalFailingStudents > 0) {
                $parts[] = "{$totalFailingStudents} students falling below the 75% passing mark";
            }
            if ($totalMissingTasks > 0) {
                $parts[] = "{$totalMissingTasks} missing past-due submissions";
            }

            $insight = "Action needed: You have " . implode(" and ", $parts) . ". ";

            if ($mostStrugglingCourse) {
                $insight .= "'{$mostStrugglingCourse}' needs the most immediate attention.";
            }

            $aiInsight = $insight;
        }

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'active_courses' => $activeCourses,
                'total_students' => $totalStudents,
                'pending_submissions' => $pendingSubmissions,
            ],
            'grading_queue' => $gradingQueue,
            'upcoming_assignments' => $upcomingAssignments,
            'broadcast_courses' => $broadcastCourses,
            'classroom_health' => collect($classroomHealth)->sortByDesc('failing_count')->values()->toArray(),
            'ai_insight' => $aiInsight
        ]);
    }
}