<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\Assignment;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'is_impersonating' => $request->session()->has('impersonate_admin_id'),
                
                'notifications' => $user ? $user->notifications()->take(10)->get() : [],
                'unread_count' => $user ? $user->unreadNotifications()->count() : 0,
                
                // 1. ADMIN: Pending Materials
                'pending_materials_count' => ($user && $user->role === 'admin') 
                    ? Lesson::where('approval_status', 'pending')->count() 
                    : 0,

                // 2. TEACHER: Pending Submissions to Grade
                'pending_grading_count' => ($user && $user->role === 'teacher')
                    ? Submission::whereHas('assignment.course', function($q) use ($user) {
                        $q->where('teacher_id', $user->id);
                    })->whereNull('grade')->count()
                    : 0,

                // 3. STUDENT: Pending Assignments (To Do)
                'pending_tasks_count' => ($user && $user->role === 'student')
                    ? Assignment::whereIn('course_id', function($q) use ($user) {
                        $q->select('course_id')->from('enrollments')->where('user_id', $user->id)->where('status', 'approved');
                    })
                    ->where(function ($q) {
                        $q->where('closing_date', '>=', now())->orWhereNull('closing_date');
                    })
                    ->whereDoesntHave('submissions', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })->count()
                    : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ]);
    }
}