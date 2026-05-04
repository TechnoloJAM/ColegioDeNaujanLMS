<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\Submission;
use App\Models\Assignment;
use App\Models\Enrollment; // ADDED THIS
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
            ->take(5)
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
            ->limit(4)
            ->get();

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'active_courses' => $activeCourses,
                'total_students' => $totalStudents,
                'pending_submissions' => $pendingSubmissions,
            ],
            'grading_queue' => $gradingQueue,
            'upcoming_assignments' => $upcomingAssignments
        ]);
    }
}