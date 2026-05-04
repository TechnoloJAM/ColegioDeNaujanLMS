<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\Submission;
use App\Notifications\EnrollmentApproved;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function create() { return Inertia::render('Teacher/CourseCreate'); }

    private function generateUniqueCode()
    {
        do { $code = strtoupper(substr(md5(uniqid()), 0, 6)); } while (Course::where('enrollment_code', $code)->exists());
        return $code;
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        Course::create([
            'teacher_id' => Auth::id(),
            'enrollment_code' => $this->generateUniqueCode(),
            'title' => $request->title,
            'description' => $request->description,
            'difficulty_level' => $request->difficulty_level,
            'thumbnail' => $thumbnailPath ? '/storage/' . $thumbnailPath : null,
            
            // FIX: Changed from false to true so courses are LIVE by default!
            'is_published' => true, 
        ]);

        return redirect()->route('teacher.courses.index');
    }

    public function index()
    {
        $courses = Course::where('teacher_id', Auth::id())->orderBy('created_at', 'desc')->get();
        return Inertia::render('Teacher/CourseList', ['courses' => $courses]);
    }
    
    public function show(Course $course)
    {
        $user = Auth::user();

        if ($user->role === 'student') abort(403, 'Students must access courses through the student dashboard.');
        if ($user->role === 'teacher' && $course->teacher_id !== $user->id) abort(403, 'Unauthorized access.');

        $course->load([
            'assignments', 
            'lessons', 
            'announcements.user',
            'announcements.comments.user',
            'enrollments.user' 
        ]); 

        return Inertia::render('Teacher/CourseManage', ['course' => $course]);
    }

    public function approveStudent(Request $request, Course $course, $userId)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $enrollment = $course->enrollments()->where('user_id', $userId)->firstOrFail();
        $enrollment->update(['status' => 'approved']);
        User::findOrFail($userId)->notify(new EnrollmentApproved($course));
        return back()->with('success', 'Student approved and notified!');
    }

    public function removeStudent(Request $request, Course $course, $userId)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $course->enrollments()->where('user_id', $userId)->delete(); 
        return back()->with('success', 'Student removed from class.');
    }

    public function edit(Request $request, Course $course)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $backUrl = $request->query('source') === 'manage' ? route('teacher.courses.show', $course->id) : route('teacher.courses.index');
        return Inertia::render('Teacher/CourseEdit', ['course' => $course, 'backUrl' => $backUrl]);
    }

    public function update(Request $request, Course $course)
    {
        $user = Auth::user();
        if ($course->teacher_id !== $user->id && $user->role !== 'admin') abort(403);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced,final',
            'teacher_id' => 'nullable|exists:users,id', 
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'difficulty_level', 'teacher_id']);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) Storage::disk('public')->delete(str_replace('/storage/', '', $course->thumbnail));
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $data['thumbnail'] = '/storage/' . $path;
        }

        $course->update($data); 
        return back()->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        $user = Auth::user();
        if ($course->teacher_id !== $user->id && $user->role !== 'admin') abort(403);
        $course->delete();
        return redirect()->route('teacher.courses.index')->with('success', 'Course deleted successfully.');
    }

    public function gradebook(Request $request, ?Course $course = null)
    {
        $teacherId = Auth::id();
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            $allCourses = Course::select('id', 'title')->orderBy('created_at', 'desc')->get();
        } else {
            $allCourses = Course::where('teacher_id', $teacherId)->select('id', 'title')->orderBy('created_at', 'desc')->get();
        }

        if ($allCourses->isEmpty()) {
            return Inertia::render('Teacher/Gradebook', ['course' => null, 'courses' => [], 'assignments' => [], 'students' => []]);
        }

        if (!$course || ($course->teacher_id !== $teacherId && $user->role !== 'admin')) {
            $course = Course::find($allCourses->first()->id);
        }

        $assignments = $course->assignments()->orderBy('created_at')->get();

        // THE ORIGINAL QUERY: Using whereHas to strictly fetch from the Users table based on enrolledCourses
        $students = User::whereHas('enrolledCourses', function($query) use ($course) {
            $query->where('course_id', $course->id)->where('enrollments.status', 'approved');
        })->with(['submissions' => function($query) use ($course) {
            $query->whereHas('assignment', function($q) use ($course) {
                $q->where('course_id', $course->id);
            });
        }])->orderBy('name')->get();

        return Inertia::render('Teacher/Gradebook', [
            'course' => $course, 
            'courses' => $allCourses, 
            'assignments' => $assignments, 
            'students' => $students
        ]);
    }

    public function togglePublish(Course $course)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $course->update(['is_published' => !$course->is_published]);
        
        $msg = $course->is_published 
            ? 'Course is now LIVE and visible to approved students.' 
            : 'Course is now HIDDEN. Students can no longer access it.';
            
        return back()->with('success', $msg);
    }

    public function autosaveGrade(Request $request, Course $course)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);

        $request->validate([
            'student_id' => 'required|exists:users,id',
            'assignment_id' => 'required|exists:assignments,id',
            'grade' => 'nullable|numeric|min:0'
        ]);

        Submission::updateOrCreate(
            ['user_id' => $request->student_id, 'assignment_id' => $request->assignment_id],
            ['grade' => $request->grade, 'text_content' => 'Graded directly via Smart Gradebook.']
        );

        return response()->json(['status' => 'success']);
    }
}