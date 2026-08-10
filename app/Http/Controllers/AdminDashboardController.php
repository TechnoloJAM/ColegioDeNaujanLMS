<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\Recommendation; 
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $fortyEightHoursAgo = now()->subHours(48);
        $fourteenDaysAgo = now()->subDays(14);
        $startOfMonth = now()->startOfMonth();

        // 1. ACTIVE LEARNERS: Unique students who submitted work in the last 7 days
        $activeLearners = Submission::where('created_at', '>=', now()->subDays(7))
            ->distinct('user_id')
            ->count('user_id');

        // 2. REGISTERED USERS (Corrected Math for accurate top cards)
        $activeMembers = User::where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->where(function($q) {
                // Admins don't need a school ID, everyone else does to be 'active'
                $q->whereNotNull('school_id')->orWhere('role', 'admin');
            })->count();

        $pendingOnboarding = User::where('status', 'active')
            ->where(function($q) {
                $q->whereNull('email_verified_at')
                  ->orWhere(function($sub) {
                      $sub->whereNull('school_id')->where('role', '!=', 'admin');
                  });
            })->count();
            
        $suspendedUsers = User::where('status', 'suspended')->count();

        // 3. ACADEMIC VELOCITY: System-wide throughput for the current month
        $submissionsProcessed = Submission::where('created_at', '>=', $startOfMonth)->count();
        $aiInterventions = Recommendation::where('created_at', '>=', $startOfMonth)->count();

        // 4. CLASSROOM HEALTH: Measuring stagnant vs active environments
        $totalPublishedCourses = Course::where('is_published', true)->count();
        
        $healthyCourses = Course::where('is_published', true)
            ->where(function($q) use ($fourteenDaysAgo) {
                $q->whereHas('lessons', function($sq) use ($fourteenDaysAgo) {
                    $sq->where('created_at', '>=', $fourteenDaysAgo);
                })
                ->orWhereHas('assignments', function($sq) use ($fourteenDaysAgo) {
                    $sq->where('created_at', '>=', $fourteenDaysAgo);
                })
                ->orWhereHas('assignments.submissions', function($sq) use ($fourteenDaysAgo) {
                    $sq->where('created_at', '>=', $fourteenDaysAgo);
                });
            })->count();

        $stagnantCourses = $totalPublishedCourses - $healthyCourses;

        // 5. CRITICAL BOTTLENECKS: Queue Aging > 48 hours
        $staleEnrollmentsCount = Enrollment::where('status', 'pending')
            ->where('created_at', '<', $fortyEightHoursAgo)
            ->count();
            
        $staleMaterialsCount = Lesson::where('approval_status', 'pending')
            ->where('created_at', '<', $fortyEightHoursAgo)
            ->count();
            
        $criticalBottlenecks = $staleEnrollmentsCount + $staleMaterialsCount;

        // ==========================================
        // ADMINISTRATIVE ACTION CENTER PIPELINE
        // ==========================================
        $actionItems = collect();

        // A. High Severity: Stale Materials
        $staleLessons = Lesson::with('course')
            ->where('approval_status', 'pending')
            ->where('created_at', '<', $fortyEightHoursAgo)
            ->take(3)
            ->get();
            
        foreach($staleLessons as $lesson) {
            $courseTitle = $lesson->course ? $lesson->course->title : 'Deleted Course';
            $actionItems->push([
                'id' => 'mat_'.$lesson->id,
                'severity' => 'high',
                'description' => "Material in '{$courseTitle}' pending review > 48 hours.",
                'link' => route('admin.materials')
            ]);
        }

        // B. High Severity: Stale Enrollments
        $staleEnrolls = Enrollment::with('course')
            ->selectRaw('course_id, count(*) as total')
            ->where('status', 'pending')
            ->where('created_at', '<', $fortyEightHoursAgo)
            ->groupBy('course_id')
            ->take(3)
            ->get();
            
        foreach($staleEnrolls as $enroll) {
            $courseTitle = $enroll->course ? $enroll->course->title : 'Deleted Course';
            $actionItems->push([
                'id' => 'enr_'.$enroll->course_id,
                'severity' => 'high',
                'description' => "{$enroll->total} students pending enrollment in '{$courseTitle}' for > 48h.",
                'link' => route('admin.courses.index') 
            ]);
        }

        // C. Medium Severity: Stagnant Courses
        $stagnantList = Course::where('is_published', true)
            ->whereDoesntHave('lessons', fn($q) => $q->where('created_at', '>=', $fourteenDaysAgo))
            ->whereDoesntHave('assignments', fn($q) => $q->where('created_at', '>=', $fourteenDaysAgo))
            ->whereDoesntHave('assignments.submissions', fn($q) => $q->where('created_at', '>=', $fourteenDaysAgo))
            ->take(4)
            ->get();
            
        foreach($stagnantList as $course) {
            $actionItems->push([
                'id' => 'stag_'.$course->id,
                'severity' => 'medium',
                'description' => "Classroom '{$course->title}' completely inactive > 14 days.",
                'link' => route('admin.courses.index')
            ]);
        }

        // Bundle Health & Velocity Stats (Variables correctly passed)
        $stats = [
            'activeMembers' => $activeMembers,
            'pendingOnboarding' => $pendingOnboarding,
            'suspendedUsers' => $suspendedUsers,
            'activeLearners' => $activeLearners,
            'submissionsProcessed' => $submissionsProcessed,
            'aiInterventions' => $aiInterventions,
            'healthyCourses' => $healthyCourses,
            'stagnantCourses' => $stagnantCourses,
            'criticalBottlenecks' => $criticalBottlenecks,
        ];

        // ==========================================
        // DYNAMIC MONTH-BY-MONTH CALENDAR CHART 
        // ==========================================
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);
        $date = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $date->daysInMonth;

        $usersDaily = User::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get()
            ->groupBy(fn($val) => Carbon::parse($val->created_at)->format('j'));

        $enrollsDaily = Enrollment::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get()
            ->groupBy(fn($val) => Carbon::parse($val->created_at)->format('j'))
            ->map(fn($row) => $row->count());

        $labels = [];
        $totalData = [];
        $activeData = [];
        $suspendedData = [];
        $enrollmentsData = [];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $labels[] = $date->format('M') . ' ' . $i;
            $dailyUsers = $usersDaily->get($i, collect());
            
            $totalData[] = $dailyUsers->count();
            
            // Chart Math Corrected: Matches the top cards strict filtering
            $activeData[] = $dailyUsers->where('status', 'active')
                                       ->whereNotNull('email_verified_at')
                                       ->filter(fn($u) => $u->role === 'admin' || $u->school_id !== null)
                                       ->count();
                                       
            $suspendedData[] = $dailyUsers->where('status', 'suspended')->count();
            $enrollmentsData[] = $enrollsDaily->get($i, 0);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'demographics' => [
                'labels' => ['Students', 'Teachers', 'Admins'],
                'data' => [
                    User::where('role', 'student')->count(),
                    User::where('role', 'teacher')->count(),
                    User::where('role', 'admin')->count()
                ]
            ],
            'chartData' => [
                'labels' => $labels,
                'total' => $totalData,
                'active' => $activeData,
                'suspended' => $suspendedData,
                'enrollments' => $enrollmentsData,
            ],
            'currentMonth' => (int) $month,
            'currentYear' => (int) $year,
            'monthName' => $date->format('F Y'),
            'actionItems' => $actionItems->values()->toArray()
        ]);
    }

    public function users()
    {
        return Inertia::render('Admin/UserManagement', [
            'users' => User::with('enrolledCourses:id,title')
                ->select('id', 'name', 'email', 'role', 'status', 'suspension_reason', 'school_id', 'program', 'created_at', 'contact_number', 'avatar')
                ->orderBy('name')
                ->paginate(15),
            'courses' => \App\Models\Course::select('id', 'title')->orderBy('title')->get()
        ]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,teacher,student',
            'school_id' => 'nullable|string|max:50',
            'program' => 'nullable|string|max:100',
            'contact_number' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:255',
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'school_id' => $request->school_id,
            'program' => $request->program,
            'contact_number' => $request->contact_number,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), 
        ]);

        return back()->with('success', ucfirst($request->role) . ' account created and automatically verified.');
    }

    public function bulkToggleUserStatus(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|in:suspend,reactivate',
            'reason' => 'required_if:action,suspend|nullable|string|max:500',
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect Admin password. Action denied.']);
        }

        if (in_array(auth()->id(), $request->user_ids) && $request->action === 'suspend') {
            return back()->withErrors(['password' => 'You cannot suspend your own admin account.']);
        }

        if ($request->action === 'suspend') {
            User::whereIn('id', $request->user_ids)->update([
                'status' => 'suspended',
                'suspension_reason' => $request->reason
            ]);
            $msg = ' suspended.';
        } else {
            User::whereIn('id', $request->user_ids)->update([
                'status' => 'active',
                'suspension_reason' => null
            ]);
            $msg = ' reactivated.';
        }

        return back()->with('success', count($request->user_ids) . ' user(s)' . $msg);
    }

    public function bulkDestroyUsers(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect Admin password. Action denied.']);
        }

        if (in_array(auth()->id(), $request->user_ids)) {
            return back()->withErrors(['password' => 'You cannot permanently delete your own admin account.']);
        }

        User::whereIn('id', $request->user_ids)->delete();

        return back()->with('success', count($request->user_ids) . ' user(s) permanently deleted.');
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:admin,teacher,student',
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect Admin password. Action denied.']);
        }

        if ($user->id === auth()->id() && $request->role !== 'admin') {
            return back()->withErrors(['password' => 'You cannot demote your own admin account.']);
        }

        $user->update(['role' => $request->role]);

        return back()->with('success', "{$user->name} is now a " . ucfirst($request->role) . ".");
    }

    public function resetUserPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', Rules\Password::defaults()],
            'admin_password' => 'required|string'
        ]);

        if (!Hash::check($request->admin_password, auth()->user()->password)) {
            return back()->withErrors(['admin_password' => 'Incorrect Admin password. Action denied.']);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', "Password for {$user->name} has been successfully reset.");
    }

    public function impersonate(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect Admin password. Action denied.']);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['password' => 'You are already logged into this account.']);
        }

        $adminId = auth()->id();
        auth()->login($user);
        session()->put('impersonate_admin_id', $adminId);
        
        return redirect()->route('dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function restoreAdminSession()
    {
        if (session()->has('impersonate_admin_id')) {
            $adminId = session()->get('impersonate_admin_id');
            session()->forget('impersonate_admin_id');
            auth()->loginUsingId($adminId);
            return redirect()->route('admin.users.index')->with('success', 'Welcome back to the Admin Dashboard.');
        }
        
        return redirect()->route('dashboard');
    }

    public function courses()
    {
        $courses = Course::with(['teacher:id,name', 'enrollments'])
            ->withCount('lessons', 'assignments')
            ->latest()
            ->get();

        $teachers = User::where('role', 'teacher')->where('status', 'active')->select('id', 'name')->get();

        return Inertia::render('Admin/CourseOversight', [
            'courses' => $courses,
            'teachers' => $teachers
        ]);
    }

    public function storeCourse(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'courses' => 'required|array|min:1',
            'courses.*.title' => 'required|string|max:255',
            'courses.*.description' => 'nullable|string',
            'courses.*.difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'courses.*.days' => 'nullable|array',
            'courses.*.start_time' => 'nullable', // Removed buggy Laravel time rules
            'courses.*.end_time' => 'nullable',   // Removed buggy Laravel time rules
            'courses.*.room' => 'nullable|string|max:100',
        ]);

        $teacherId = $request->teacher_id;
        $coursesToSave = $request->courses;

        // 1. Conflict Prevention Shield & Time Validation
        foreach ($coursesToSave as $index => $c) {
            
            // FIX: Manual time check to prevent 500 Fatal Errors
            if (!empty($c['start_time']) && !empty($c['end_time']) && $c['start_time'] >= $c['end_time']) {
                return back()->withErrors(["courses.{$index}.end_time" => "End time must be after the start time."]);
            }

            if (!empty($c['days']) && !empty($c['start_time']) && !empty($c['end_time'])) {
                $conflict = $this->checkScheduleConflict($teacherId, $c['days'], $c['start_time'], $c['end_time'], $c['room'] ?? null);
                
                if ($conflict) {
                    return back()->withErrors(["courses.{$index}.start_time" => "Conflict in Tab " . ($index + 1) . ": {$conflict}"]);
                }
            }
        }

        // 2. Save Everything if no conflicts exist
        foreach ($coursesToSave as $c) {
            Course::create([
                'teacher_id' => $teacherId,
                'enrollment_code' => strtoupper(substr(md5(uniqid()), 0, 6)),
                'title' => $c['title'],
                'description' => $c['description'] ?? null,
                'difficulty_level' => $c['difficulty_level'],
                'days' => $c['days'] ?? null,
                'start_time' => $c['start_time'] ?? null,
                'end_time' => $c['end_time'] ?? null,
                'room' => $c['room'] ?? null,
                'is_published' => false,
            ]);
        }

        return back()->with('success', count($coursesToSave) . ' Subject(s) distributed and scheduled successfully.');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'teacher_id' => 'required|exists:users,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'days' => 'nullable|array',
            'start_time' => 'nullable', // Removed buggy Laravel time rules
            'end_time' => 'nullable',   // Removed buggy Laravel time rules
            'room' => 'nullable|string|max:100',
        ]);

        // FIX: Manual time check 
        if (!empty($request->start_time) && !empty($request->end_time) && $request->start_time >= $request->end_time) {
            return back()->withErrors(["end_time" => "End time must be after the start time."]);
        }

        // Conflict Shield (Excluding the current course so it doesn't conflict with itself)
        if (!empty($request->days) && !empty($request->start_time) && !empty($request->end_time)) {
            $conflict = $this->checkScheduleConflict($request->teacher_id, $request->days, $request->start_time, $request->end_time, $request->room, $course->id);
            if ($conflict) {
                return back()->withErrors(["start_time" => "Schedule Conflict: {$conflict}"]);
            }
        }

        $data = $request->only('title', 'description', 'difficulty_level', 'teacher_id', 'days', 'start_time', 'end_time', 'room');

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                $oldPath = str_replace('/storage/', '', $course->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $data['thumbnail'] = '/storage/' . $path;
        }

        $course->update($data);

        return back()->with('success', 'Course and schedule updated successfully.');
    }

    private function checkScheduleConflict($teacherId, $days, $startTime, $endTime, $room, $excludeCourseId = null)
    {
        $conflicts = Course::where(function($q) use ($teacherId, $room) {
                $q->where('teacher_id', $teacherId);
                if (!empty($room)) {
                    $q->orWhere('room', $room);
                }
            })
            ->when($excludeCourseId, function($q) use ($excludeCourseId) {
                $q->where('id', '!=', $excludeCourseId);
            })
            ->where(function($q) use ($startTime, $endTime) {
                // The mathematical formula for Time Overlap
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->get();

        foreach ($conflicts as $conflict) {
            if ($conflict->days) {
                $conflictDays = is_string($conflict->days) ? json_decode($conflict->days, true) : $conflict->days;
                
                if (is_array($conflictDays) && count(array_intersect($days, $conflictDays)) > 0) {
                    if ($conflict->teacher_id == $teacherId) {
                        return "Instructor is already teaching '{$conflict->title}' during this time.";
                    } else {
                        return "Room '{$room}' is occupied by '{$conflict->title}' during this time.";
                    }
                }
            }
        }

        return null; 
    }

    public function bulkDestroyCourses(Request $request)
    {
        $request->validate([
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect Admin password. Action denied.']);
        }

        $courses = Course::whereIn('id', $request->course_ids)->get();
        
        foreach ($courses as $course) {
            $course->forceDelete(); 
        }

        return back()->with('success', count($request->course_ids) . ' course(s) and their related files permanently deleted.');
    }

    public function materials()
    {
        $materials = Lesson::with(['course:id,title,teacher_id', 'course.teacher:id,name'])
            ->latest()
            ->get();

        $requireApproval = Setting::where('key', 'require_material_approval')->value('value') ?? 'true';

        return Inertia::render('Admin/MaterialApproval', [
            'materials' => $materials,
            'requireApproval' => $requireApproval === 'true'
        ]);
    }

    public function toggleMaterialApproval(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'The provided password does not match our records.']);
        }

        $setting = Setting::firstOrCreate(['key' => 'require_material_approval'], ['value' => 'true']);
        $newValue = $setting->value === 'true' ? 'false' : 'true';
        $setting->update(['value' => $newValue]);
        
        if ($newValue === 'false') {
            Lesson::where('approval_status', 'pending')->update(['approval_status' => 'approved']);
        }
        
        $status = $newValue === 'true' ? 'enabled' : 'disabled';
        return back()->with('success', "Material approval system is now {$status}. Pending materials updated.");
    }

    public function gradesOverview()
    {
        $courses = Course::with(['teacher:id,name', 'assignments'])
            ->with(['enrollments' => function($q) {
                $q->where('status', 'approved')->with(['user' => function($userQ) {
                    $userQ->with(['submissions' => function($subQ) {
                        $subQ->with('assignment:id,type');
                    }]);
                }]);
            }])
            ->latest()
            ->get();

        $formattedData = $courses->map(function ($course) {
            $assignments = $course->assignments;
            $totalCoursePoints = $assignments->sum('points');
            
            $maxAssignment = $assignments->where('type', 'assignment')->sum('points');
            $maxActivity = $assignments->where('type', 'activity')->sum('points');
            $maxPT = $assignments->where('type', 'performance_task')->sum('points');

            $studentsData = $course->enrollments->filter(function($enrollment) {
                return $enrollment->user !== null;
            })->map(function ($enrollment) use ($course, $totalCoursePoints) {
                
                $student = $enrollment->user;
                $studentTotal = 0;
                $assignmentScore = 0;
                $activityScore = 0;
                $ptScore = 0;

                if ($course->assignments->isNotEmpty()) {
                     $courseAssignmentIds = $course->assignments->pluck('id')->toArray();
                     $submissions = $student->submissions->whereIn('assignment_id', $courseAssignmentIds);

                     foreach($submissions as $sub) {
                         $grade = (float)$sub->grade;
                         $type = $sub->assignment ? $sub->assignment->type : null;
                         
                         $studentTotal += $grade;

                         if ($type === 'assignment') $assignmentScore += $grade;
                         elseif ($type === 'activity') $activityScore += $grade;
                         elseif ($type === 'performance_task') $ptScore += $grade;
                     }
                }

                $percentage = $totalCoursePoints > 0 ? round(($studentTotal / $totalCoursePoints) * 100, 1) : 0;

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'total_score' => $studentTotal,
                    'assignment_score' => $assignmentScore,
                    'activity_score' => $activityScore,
                    'pt_score' => $ptScore,
                    'percentage' => $percentage
                ];
            });

            return [
                'id' => $course->id,
                'title' => $course->title,
                'enrollment_code' => $course->enrollment_code,
                'teacher' => $course->teacher->name ?? 'Unassigned',
                'difficulty_level' => $course->difficulty_level,
                'total_points' => $totalCoursePoints,
                'max_assignment' => $maxAssignment,
                'max_activity' => $maxActivity,
                'max_pt' => $maxPT,
                'students' => $studentsData->sortByDesc('percentage')->values()->toArray() 
            ];
        });

        return Inertia::render('Admin/GradesOverview', [
            'courses' => $formattedData
        ]);
    }
}