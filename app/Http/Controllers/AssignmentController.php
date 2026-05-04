<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Notifications\AssignmentGraded;
use App\Notifications\NewAssignmentPosted;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function index()
    {
        $courses = Course::where('teacher_id', Auth::id())
            ->with(['assignments' => function($q) {
                $q->withCount(['submissions as ungraded_count' => function ($sq) {
                    $sq->whereNull('grade');
                }]);
            }])
            ->latest()
            ->get()
            ->map(function ($course) {
                $course->ungraded_count = $course->assignments->sum('ungraded_count');
                return $course;
            });

        return Inertia::render('Teacher/AssignmentList', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request, Course $course)
    {
        if ($course->teacher_id !== Auth::id()) abort(403);

        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,performance_task',
            'points' => 'required|integer|min:0',
            'due_date' => 'required|date',
            'closing_date' => 'nullable|date|after_or_equal:due_date',
            'description' => 'nullable|string',
            'files.*' => 'nullable|file|max:15360' 
        ]);

        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('assignments', 'public');
            }
        }

        $assignment = $course->assignments()->create([
            'title' => $request->title,
            'type' => $request->type,
            'points' => $request->points,
            'due_date' => $request->due_date,
            'closing_date' => $request->closing_date,
            'description' => $request->description,
            'attachment_paths' => !empty($filePaths) ? json_encode($filePaths) : null,
        ]);

        if ($course->is_published) {
            foreach ($course->enrollments()->where('status', 'approved')->get() as $enrollment) {
                if ($enrollment->user) {
                    $enrollment->user->notify(new NewAssignmentPosted($assignment));
                }
            }
        }

        return back()->with('success', 'Task created successfully.');
    }

    public function show(Request $request, Assignment $assignment)
    {
        $assignment->load('course');
        
        if ($assignment->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $source = $request->query('source');
        $backUrl = $source === 'course' 
            ? route('teacher.courses.show', $assignment->course_id) 
            : route('teacher.assignments.index');

        $submissions = $assignment->submissions()->with('student')->get();
        $enrolledStudentIds = $assignment->course->enrollments()->where('status', 'approved')->pluck('user_id');

        $toBeGraded = $submissions->whereNull('grade')->values();
        $graded = $submissions->whereNotNull('grade')->values();

        return Inertia::render('Teacher/AssignmentManage', [
            'assignment' => $assignment,
            'course' => $assignment->course,
            'toBeGraded' => $toBeGraded,
            'graded' => $graded,
            'backUrl' => $backUrl
        ]);
    }

    public function update(Request $request, Assignment $assignment)
    {
        if ($assignment->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);

        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,activity,performance_task',
            'points' => 'required|integer|min:0',
            'due_date' => 'required|date',
            'closing_date' => 'nullable|date|after_or_equal:due_date',
            'description' => 'nullable|string',
            'files.*' => 'nullable|file|max:15360'
        ]);

        $data = $request->only(['title', 'type', 'points', 'due_date', 'closing_date', 'description']);

        if ($request->hasFile('files')) {
            if ($assignment->attachment_paths) {
                $oldPaths = json_decode($assignment->attachment_paths, true);
                if (is_array($oldPaths)) {
                    foreach ($oldPaths as $oldPath) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }
            $filePaths = [];
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('assignments', 'public');
            }
            $data['attachment_paths'] = json_encode($filePaths);
        }

        $assignment->update($data);
        return back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Assignment $assignment)
    {
        if ($assignment->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $assignment->delete();
        return redirect()->route('teacher.courses.show', $assignment->course_id)->with('success', 'Task deleted successfully.');
    }

    public function gradeSubmission(Request $request, Submission $submission)
    {
        if ($submission->assignment->course->teacher_id !== Auth::id()) abort(403, 'Unauthorized grading attempt.');

        $request->validate([
            'grade' => 'required|numeric|min:0|max:' . $submission->assignment->points,
            'feedback' => 'nullable|string'
        ]);

        $submission->update(['grade' => $request->grade, 'feedback' => $request->feedback]);

        if ($submission->user) $submission->user->notify(new AssignmentGraded($submission));

        return back()->with('success', 'Grade submitted successfully.');
    }
}