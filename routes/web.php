<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeacherDashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AIChatController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CalendarController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'status' => session('status'),
    ]);
});

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/register/onboarding', [GoogleAuthController::class, 'onboarding'])->name('register.onboarding');
    Route::post('/register/complete', [GoogleAuthController::class, 'completeRegistration'])->name('register.complete');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/admin/restore-session', [AdminDashboardController::class, 'restoreAdminSession'])->name('admin.restore-session');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $role = auth()->user()->role;
        
        if ($role === 'admin') return redirect()->route('admin.dashboard');
        if ($role === 'teacher') return redirect()->route('teacher.dashboard');
        
        if ($role === 'pending_teacher') {
            return redirect('/')->with('status', 'Your teacher account is pending administrator approval.');
        }
        
        if (empty(auth()->user()->school_id)) {
            return redirect()->route('register.onboarding');
        }
        
        return redirect()->route('student.dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/announcements/{announcement}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    
    Route::post('/ai/chat', [AIChatController::class, 'chat'])->name('ai.chat');

    Route::post('/notifications/mark-all-as-read', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.markAllRead');

    Route::delete('/notifications/{id}', function ($id) {
        auth()->user()->notifications()->where('id', $id)->delete();
        return back();
    })->name('notifications.destroy');
});

// STUDENT ROUTES
Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
    Route::get('/courses', [StudentController::class, 'courses'])->name('courses');
    Route::post('/courses/join', [StudentController::class, 'join'])->name('courses.join');
    Route::get('/courses/{course}', [StudentController::class, 'show'])->name('courses.show');
    Route::delete('/courses/{course}/leave', [StudentController::class, 'leave'])->name('courses.leave');
    Route::get('/my-assignments', [StudentController::class, 'assignments'])->name('assignments');
});

// SHARED SUBMISSION ROUTES
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/assignments/{assignment}/submit', [StudentController::class, 'submit'])->name('assignments.submit');
    Route::post('/assignments/{assignment}/unsubmit', [StudentController::class, 'unsubmit'])->name('assignments.unsubmit');
});

// TEACHER ROUTES
Route::middleware(['auth', 'verified', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
    Route::resource('courses', CourseController::class);
    Route::patch('/courses/{course}/enrollments/{user}', [CourseController::class, 'approveStudent'])->name('courses.enrollments.approve');
    Route::delete('/courses/{course}/enrollments/{user}', [CourseController::class, 'removeStudent'])->name('courses.enrollments.destroy');
    Route::post('/courses/{course}/lessons', [LessonController::class, 'store'])->name('lessons.store');
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
    Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/courses/{course}/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::post('/submissions/{submission}/grade', [AssignmentController::class, 'gradeSubmission'])->name('submissions.grade');
    Route::post('/courses/{course}/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::patch('/lessons/{lesson}/request-unarchive', [LessonController::class, 'teacherUnarchive'])->name('lessons.unarchive');
    Route::post('/lessons/{lesson}/resubmit', [LessonController::class, 'resubmit'])->name('lessons.resubmit');
    Route::post('/courses/{course}/clone', [CourseController::class, 'clone'])->name('courses.clone');
    
    // FIX: Removed the extra /teacher/ and teacher. so it matches perfectly
    Route::patch('/courses/{course}/toggle-publish', [CourseController::class, 'togglePublish'])->name('courses.toggle-publish');
    
    // GRADEBOOK ROUTES
    Route::get('/gradebook/{course?}', [CourseController::class, 'gradebook'])->name('gradebook.index');
    Route::post('/courses/{course}/gradebook/autosave', [CourseController::class, 'autosaveGrade'])->name('gradebook.autosave');
});

// ADMIN ROUTES
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users.index');
    Route::post('/users', [AdminDashboardController::class, 'storeUser'])->name('users.store');
    Route::post('/users/bulk-status', [AdminDashboardController::class, 'bulkToggleUserStatus'])->name('users.bulk-toggle-status');
    Route::post('/users/bulk-destroy', [AdminDashboardController::class, 'bulkDestroyUsers'])->name('users.bulk-destroy');
    
    // Secure Role, Password, and Impersonation
    Route::patch('/users/{user}/role', [AdminDashboardController::class, 'updateRole'])->name('users.update-role');
    Route::patch('/users/{user}/reset-password', [AdminDashboardController::class, 'resetUserPassword'])->name('users.reset-password');
    Route::post('/users/{user}/impersonate', [AdminDashboardController::class, 'impersonate'])->name('users.impersonate');
    
    // Course Oversight
    Route::get('/courses', [AdminDashboardController::class, 'courses'])->name('courses.index');
    Route::post('/courses', [AdminDashboardController::class, 'storeCourse'])->name('courses.store');
    Route::patch('/courses/{course}', [AdminDashboardController::class, 'updateCourse'])->name('courses.update');
    
    // Secure Bulk/Single Course Actions
    Route::post('/courses/bulk-status', [AdminDashboardController::class, 'bulkToggleCourseStatus'])->name('courses.bulk-toggle-status');
    Route::post('/courses/bulk-destroy', [AdminDashboardController::class, 'bulkDestroyCourses'])->name('courses.bulk-destroy');
    
    // Material Approval
    Route::get('/materials', [AdminDashboardController::class, 'materials'])->name('materials');
    Route::post('/settings/material-approval', [AdminDashboardController::class, 'toggleMaterialApproval'])->name('settings.material-approval');
    Route::post('/lessons/bulk-approve', [LessonController::class, 'bulkApprove'])->name('lessons.bulk-approve');
    Route::patch('/lessons/{lesson}/approve', [LessonController::class, 'approve'])->name('lessons.approve');
    Route::patch('/materials/{lesson}/archive', [LessonController::class, 'archive'])->name('lessons.archive');
    Route::patch('/lessons/{lesson}/unarchive', [LessonController::class, 'unarchive'])->name('lessons.unarchive');
    Route::patch('/lessons/{lesson}/reject', [LessonController::class, 'reject'])->name('lessons.reject');
    Route::patch('/lessons/{lesson}/update', [LessonController::class, 'update'])->name('lessons.update');
    
    // Grades
    Route::get('/grades', [AdminDashboardController::class, 'gradesOverview'])->name('grades.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
});

require __DIR__.'/auth.php';