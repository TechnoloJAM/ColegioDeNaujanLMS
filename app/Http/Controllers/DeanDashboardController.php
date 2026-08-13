<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Submission;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DeanDashboardController extends Controller
{
    // 1. MAIN DASHBOARD (Ultra-Compact Overview)
    public function index()
    {
        $dean = Auth::user();
        if (!$dean->department_id) {
            return Inertia::render('Dean/Dashboard', [
                'departmentName' => 'Unassigned', 'deptStats' => null, 'slaRadar' => [], 
                'heatmap' => [], 'topPerforming' => [], 'activityPulse' => []
            ]);
        }

        $teachers = User::where('role', 'teacher')->where('department_id', $dean->department_id)->get();
        $teacherIds = $teachers->pluck('id');

        // --- DEPARTMENT VITALS ---
        $activeCoursesCount = Course::whereIn('teacher_id', $teacherIds)->where('is_published', true)->count();
        $totalStudentsCount = \App\Models\Enrollment::whereHas('course', function($q) use ($teacherIds) {
            $q->whereIn('teacher_id', $teacherIds);
        })->where('status', 'approved')->distinct('user_id')->count('user_id');

        $deptStats = [
            'total_teachers' => $teachers->count(),
            'active_courses' => $activeCoursesCount,
            'total_students' => $totalStudentsCount
        ];

        // --- SLA RADAR ---
        $slaRadar = [];
        foreach ($teachers as $teacher) {
            $staleCount = Submission::whereHas('assignment.course', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })->whereNull('grade')->where('created_at', '<', now()->subDays(7))->count();
            if ($staleCount > 0) $slaRadar[] = ['teacher_name' => $teacher->name, 'stale_count' => $staleCount];
        }
        usort($slaRadar, fn($a, $b) => $b['stale_count'] <=> $a['stale_count']);

        // --- HEATMAP, TOP PERFORMING & PULSE ---
        $courses = Course::whereIn('teacher_id', $teacherIds)->where('is_published', true)->with(['teacher', 'assignments', 'enrollments' => function($q) {
            $q->where('status', 'approved')->with('user.submissions');
        }])->get();

        $heatmap = [];
        $topPerforming = [];
        $activityPulse = [];

        foreach ($courses as $course) {
            $failingCount = 0;
            $classTotalPct = 0;
            $totalStudents = $course->enrollments->count();
            $coursePoints = $course->assignments->sum('points');
            
            if ($totalStudents > 0 && $coursePoints > 0) {
                foreach ($course->enrollments as $enrollment) {
                    if ($student = $enrollment->user) {
                        $earned = $student->submissions->whereIn('assignment_id', $course->assignments->pluck('id'))->sum('grade');
                        $avg = ($earned / $coursePoints) * 100;
                        $classTotalPct += $avg;
                        if ($avg < 75) $failingCount++;
                    }
                }
                
                // Heatmap Check (>= 30% Failing)
                $failureRate = ($failingCount / $totalStudents) * 100;
                if ($failureRate >= 30) { 
                    $heatmap[] = ['id' => $course->id, 'title' => $course->title, 'teacher' => $course->teacher->name, 'failure_rate' => round($failureRate, 1), 'failing_students' => $failingCount, 'total_students' => $totalStudents];
                }

                // Top Performing Check (>= 85% Class Average)
                $classAvg = $classTotalPct / $totalStudents;
                if ($classAvg >= 85) {
                    $topPerforming[] = ['id' => $course->id, 'title' => $course->title, 'teacher' => $course->teacher->name, 'average' => round($classAvg, 1)];
                }
            }

            $lastAssignment = $course->assignments->max('created_at');
            $lastLesson = \App\Models\Lesson::where('course_id', $course->id)->max('created_at');
            $lastActivity = collect([$lastAssignment, $lastLesson])->max();
            $daysInactive = $lastActivity ? round(now()->diffInDays($lastActivity)) : 'Never';
            
            if ($daysInactive === 'Never' || $daysInactive > 14) {
                $activityPulse[] = ['course' => $course->title, 'teacher' => $course->teacher->name, 'days_inactive' => $daysInactive];
            }
        }
        usort($heatmap, fn($a, $b) => $b['failure_rate'] <=> $a['failure_rate']);
        usort($topPerforming, fn($a, $b) => $b['average'] <=> $a['average']);
        usort($activityPulse, fn($a, $b) => ($b['days_inactive'] === 'Never' ? 999 : $b['days_inactive']) <=> ($a['days_inactive'] === 'Never' ? 999 : $a['days_inactive']));

        return Inertia::render('Dean/Dashboard', [
            'departmentName' => $dean->department->name,
            'deptStats' => $deptStats,
            'slaRadar' => $slaRadar, 
            'heatmap' => $heatmap, 
            'topPerforming' => $topPerforming,
            'activityPulse' => $activityPulse
        ]);
    }

    // 2. FACULTY ROSTER PAGE (Dedicated Full Screen)
    public function faculty()
    {
        $dean = Auth::user();
        if (!$dean->department_id) return Inertia::render('Dean/Faculty', ['departmentName' => 'Unassigned', 'facultyRoster' => []]);

        $teachers = User::where('role', 'teacher')->where('department_id', $dean->department_id)->get();
        $facultyRoster = [];
        foreach($teachers as $teacher) {
            $activeClasses = Course::where('teacher_id', $teacher->id)->where('is_published', true)->count();
            $totalStudents = \App\Models\Enrollment::whereHas('course', function($q) use ($teacher) { $q->where('teacher_id', $teacher->id); })->where('status', 'approved')->distinct('user_id')->count('user_id');
            $facultyRoster[] = ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email, 'active_classes' => $activeClasses, 'total_students' => $totalStudents];
        }
        return Inertia::render('Dean/Faculty', ['departmentName' => $dean->department->name, 'facultyRoster' => $facultyRoster]);
    }

    // 3. COURSE AUDIT PAGE (Dedicated Full Screen)
    public function audit()
    {
        $dean = Auth::user();
        if (!$dean->department_id) return Inertia::render('Dean/Audit', ['departmentName' => 'Unassigned', 'auditCourses' => []]);

        $teacherIds = User::where('role', 'teacher')->where('department_id', $dean->department_id)->pluck('id');
        $auditCourses = Course::whereIn('teacher_id', $teacherIds)->with('teacher:id,name')->withCount(['enrollments' => fn($q) => $q->where('status', 'approved')])->orderBy('title')->get()
            ->map(fn($c) => ['id' => $c->id, 'title' => $c->title, 'teacher' => $c->teacher->name, 'students' => $c->enrollments_count]);

        return Inertia::render('Dean/Audit', ['departmentName' => $dean->department->name, 'auditCourses' => $auditCourses]);
    }
}