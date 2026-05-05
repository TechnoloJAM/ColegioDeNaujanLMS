<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', '6_months');

        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'suspendedUsers' => User::where('status', 'suspended')->count(),
            'students' => User::where('role', 'student')->count(),
            'teachers' => User::where('role', 'teacher')->count(),
            'totalCourses' => Course::count(),
            'activeCourses' => Course::where('is_published', true)->count(),
            'totalEnrollments' => Enrollment::count(),
            'pendingMaterials' => Lesson::where('approval_status', 'pending')->count(),
            'pendingEnrollments' => Enrollment::where('status', 'pending')->count(),
        ];

        // 2. Dynamic Line Chart Data
        $labels = [];
        $data = [];

        if ($range === '7_days') {
            for ($i = 6; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('M d');
                $data[] = Enrollment::whereDate('created_at', now()->subDays($i))->count();
            }
        } elseif ($range === '30_days') {
            for ($i = 29; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('M d');
                $data[] = Enrollment::whereDate('created_at', now()->subDays($i))->count();
            }
        } elseif ($range === 'year') {
            for ($i = 11; $i >= 0; $i--) {
                $labels[] = now()->subMonths($i)->format('M Y');
                $data[] = Enrollment::whereYear('created_at', now()->subMonths($i)->year)
                                    ->whereMonth('created_at', now()->subMonths($i)->month)
                                    ->count();
            }
        } else { // default 6_months
            for ($i = 5; $i >= 0; $i--) {
                $labels[] = now()->subMonths($i)->format('M Y');
                $data[] = Enrollment::whereYear('created_at', now()->subMonths($i)->year)
                                    ->whereMonth('created_at', now()->subMonths($i)->month)
                                    ->count();
            }
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'demographics' => [
                'labels' => ['Students', 'Teachers', 'Admins'],
                'data' => [$stats['students'], $stats['teachers'], User::where('role', 'admin')->count()]
            ],
            'enrollmentTrend' => [
                'labels' => $labels,
                'data' => $data
            ],
            'currentRange' => $range,
            'recentCourses' => Course::with('teacher')->latest()->take(5)->get()
        ]);
    }

    public function users()
    {
        return Inertia::render('Admin/UserManagement', [
            'users' => User::with('enrolledCourses:id,title')
                ->select('id', 'name', 'email', 'role', 'status', 'suspension_reason', 'school_id', 'program', 'created_at')
                ->orderBy('name')
                ->get(),
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'teacher_id' => 'required|exists:users,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        Course::create([
            'teacher_id' => $request->teacher_id,
            'enrollment_code' => strtoupper(substr(md5(uniqid()), 0, 6)),
            'title' => $request->title,
            'description' => $request->description,
            'difficulty_level' => $request->difficulty_level,
            'thumbnail' => $thumbnailPath ? '/storage/' . $thumbnailPath : null,
            'is_published' => false,
        ]);

        return back()->with('success', 'Course created and assigned successfully.');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'teacher_id' => 'required|exists:users,id',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only('title', 'description', 'difficulty_level', 'teacher_id');

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                $oldPath = str_replace('/storage/', '', $course->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $data['thumbnail'] = '/storage/' . $path;
        }

        $course->update($data);
        return back()->with('success', 'Course updated successfully.');
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
        
        // FIX #4: Approval Limbo Rescue
        // If the admin turns off approval, auto-approve any trapped pending materials.
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