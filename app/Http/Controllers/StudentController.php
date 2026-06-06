<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class StudentController extends Controller
{
    // 🪄 NEW HELPER FUNCTION: Teaches the backend to read the secret tag!
    private function isHiddenFromStudent($assignment, $enrollmentDate)
    {
        if (!$assignment->due_date) return false;
        
        // Look for the invisible sticky note in the description using the NEW tag
        if (!str_contains($assignment->description ?? '', '[RESTRICT_LATE_STUDENTS]')) {
            return false;
        }

        // Check if they enrolled after the deadline
        return Carbon::parse($enrollmentDate) > Carbon::parse($assignment->due_date);
    }

    public function dashboard()
    {
        $user = Auth::user();
        
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'approved')
            ->get()
            ->keyBy('course_id');
            
        $enrolledCourseIds = $enrollments->keys();
            
        // Fetch pending assignments and filter out the hidden ones
        $pendingAssignments = Assignment::whereIn('course_id', $enrolledCourseIds)
            ->where(function ($q) {
                $q->where('closing_date', '>=', now())
                  ->orWhereNull('closing_date');
            })
            ->whereDoesntHave('submissions', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get()
            ->reject(function($a) use ($enrollments) {
                $enrollmentDate = $enrollments[$a->course_id]->created_at ?? $enrollments[$a->course_id]->enrolled_at;
                return $this->isHiddenFromStudent($a, $enrollmentDate);
            });

        $pendingCount = $pendingAssignments->count();

        $hardCodedRecommendations = [];
        
        foreach ($enrolledCourseIds as $courseId) {
            $course = Course::find($courseId);
            if (!$course) continue;

            $assignments = Assignment::where('course_id', $courseId)->get();
            $total = 0; $earned = 0;
            
            foreach ($assignments as $a) {
                // 🛡️ Skip hidden tasks so it doesn't ruin their grade average!
                $enrollmentDate = $enrollments[$courseId]->created_at ?? $enrollments[$courseId]->enrolled_at;
                if ($this->isHiddenFromStudent($a, $enrollmentDate)) {
                    continue; 
                }

                $sub = Submission::where('user_id', $user->id)->where('assignment_id', $a->id)->first();
                if ($sub && $sub->grade !== null) {
                    $earned += $sub->grade; 
                    $total += $a->points;
                }
            }
            
            $average = ($total > 0) ? ($earned / $total) * 100 : 100;

            if ($average < 75) {
                $hardCodedRecommendations[] = [
                    'id' => $courseId,
                    'category' => 'Academic Warning',
                    'recommendation_text' => 'You are currently at risk in ' . $course->title . '. Focus on your upcoming tasks.',
                    'reasoning' => 'Your current grade average is ' . number_format($average, 1) . '%, which is below the passing mark.'
                ];
            }
        }

        // Fetch upcoming and filter out hidden ones
        $upcoming = Assignment::whereIn('course_id', $enrolledCourseIds)
            ->where(function ($q) {
                $q->where('closing_date', '>=', now())
                  ->orWhereNull('closing_date');
            })
            ->whereDoesntHave('submissions', function($q) use ($user) { 
                $q->where('user_id', $user->id); 
            })
            ->with('course:id,title')
            ->orderBy('due_date', 'asc')
            ->get()
            ->reject(function($a) use ($enrollments) {
                $enrollmentDate = $enrollments[$a->course_id]->created_at ?? $enrollments[$a->course_id]->enrolled_at;
                return $this->isHiddenFromStudent($a, $enrollmentDate);
            })
            ->take(5)
            ->values();

        $announcements = \App\Models\Announcement::whereIn('course_id', $enrolledCourseIds)
            ->with(['course:id,title', 'user:id,name'])
            ->latest()
            ->take(5)
            ->get();

        $recentGrades = Submission::where('user_id', $user->id)
            ->whereNotNull('grade')
            ->with(['assignment' => function($q) {
                $q->select('id', 'title', 'course_id', 'points')->with('course:id,title');
            }])
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Student/Dashboard', [
            'stats' => ['courses' => $enrolledCourseIds->count(), 'pending' => $pendingCount],
            'upcoming' => $upcoming,
            'announcements' => $announcements,
            'recentGrades' => $recentGrades,
            'recommendations' => $hardCodedRecommendations
        ]);
    }

    public function courses()
    {
        $user = Auth::user();
        $courses = $user->enrolledCourses()
            ->where('courses.is_published', true)
            ->with('teacher')
            ->get();
            
        return Inertia::render('Student/CourseList', ['joinedCourses' => $courses]);
    }

    public function join(Request $request)
    {
        $request->validate(['enrollment_code' => 'required|string']);
        
        $course = Course::where('enrollment_code', strtoupper($request->enrollment_code))->first();
        
        if (!$course || !$course->is_published) {
            return back()->withErrors(['enrollment_code' => 'This class is currently unavailable or the code is invalid.']);
        }

        $user = Auth::user();

        if ($user->enrolledCourses()->where('course_id', $course->id)->exists()) {
            return back()->withErrors(['enrollment_code' => 'You are already enrolled (or pending approval) in this class!']);
        }

        Enrollment::create([
            'user_id' => $user->id, 
            'course_id' => $course->id, 
            'status' => 'pending',
            'enrolled_at' => now(),
            'progress_percent' => 0,
            'is_completed' => false
        ]);
        
        return back()->with('success', 'Join request sent!');
    }

    public function show(Course $course)
    {
        if (!$course->is_published) {
            abort(403, 'This course is currently unavailable.');
        }

        $enrollment = Enrollment::where('user_id', Auth::id())->where('course_id', $course->id)->first();
        if (!$enrollment || $enrollment->status !== 'approved') abort(403, 'You are not approved for this class.');

        $now = now();

        $course->load([
            'teacher',
            'lessons' => function($q) use ($now) { 
                $q->where('approval_status', 'approved')
                  ->where(function ($query) use ($now) {
                      $query->whereNull('available_from')->orWhere('available_from', '<=', $now);
                  })
                  ->where(function ($query) use ($now) {
                      $query->whereNull('available_until')->orWhere('available_until', '>=', $now);
                  });
            },
            'assignments.submissions' => function($q) { $q->where('user_id', Auth::id()); },
            'announcements.user',
            'announcements.comments.user'
        ]);

        // 🛡️ Strip out hidden assignments before sending to the Vue file
        $enrollmentDate = $enrollment->created_at ?? $enrollment->enrolled_at;
        $filteredAssignments = $course->assignments->reject(function($a) use ($enrollmentDate) {
            return $this->isHiddenFromStudent($a, $enrollmentDate);
        })->values();
        
        $course->setRelation('assignments', $filteredAssignments);

        return Inertia::render('Student/CourseShow', ['course' => $course]);
    }

    public function leave(Course $course)
    {
        Enrollment::where('user_id', Auth::id())->where('course_id', $course->id)->delete();
        return redirect()->route('student.courses')->with('success', 'You have left the class.');
    }

    public function assignments()
    {
        $user = Auth::user();
        
        $courses = $user->enrolledCourses()
            ->where('enrollments.status', 'approved')
            ->where('courses.is_published', true)
            ->with(['assignments' => function($q) {
                $q->orderBy('due_date', 'asc');
            }, 'assignments.submissions' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->get();

        // 🛡️ Filter hidden assignments from the main Tasks list
        foreach ($courses as $course) {
            $enrollmentDate = $course->pivot->created_at ?? $course->pivot->enrolled_at;
            $filteredAssignments = $course->assignments->reject(function($a) use ($enrollmentDate) {
                return $this->isHiddenFromStudent($a, $enrollmentDate);
            })->values();
            
            $course->setRelation('assignments', $filteredAssignments);
        }

        return Inertia::render('Student/AssignmentList', ['courses' => $courses]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $isEnrolled = Enrollment::where('course_id', $assignment->course_id)
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->exists();

        if (!$isEnrolled || !$assignment->course->is_published) abort(403, 'You do not have active access to this course.');

        if ($assignment->closing_date && now() > $assignment->closing_date) {
            return back()->with('error', 'The hard deadline has passed. Submissions are locked.');
        }

        $request->validate([
            'text_content' => 'nullable|string',
            'files.*' => 'nullable|file|max:15360' 
        ]);

        $hasFiles = $request->hasFile('files');
        $hasText = !empty(trim($request->text_content ?? ''));

        if (!$hasText && !$hasFiles) {
            return back()->withErrors(['files' => 'Please write an answer or attach a file.']);
        }

        $existingSubmission = Submission::where('assignment_id', $assignment->id)->where('user_id', Auth::id())->first();

        $filePaths = [];
        if ($hasFiles) {
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('submissions', 'public');
            }
        } else if ($existingSubmission) {
            $filePaths = json_decode($existingSubmission->file_paths, true) ?? [];
        }

        Submission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => Auth::id()],
            [
                'text_content' => $request->text_content,
                'file_paths' => !empty($filePaths) ? json_encode($filePaths) : null,
            ]
        );

        return back()->with('success', 'Task submitted successfully!');
    }

    public function unsubmit(Assignment $assignment)
    {
        $isEnrolled = Enrollment::where('course_id', $assignment->course_id)
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->exists();

        if (!$isEnrolled || !$assignment->course->is_published) abort(403, 'You do not have active access to this course.');

        if ($assignment->closing_date && now() > $assignment->closing_date) {
            return back()->with('error', 'The hard deadline has passed. You can no longer modify your submission.');
        }

        $submission = Submission::where('assignment_id', $assignment->id)->where('user_id', Auth::id())->first();
        if ($submission) {
            if ($submission->grade !== null) return back()->with('error', 'Security Block: Cannot unsubmit a graded assignment.');
            $submission->delete(); 
        }
        return back()->with('success', 'Submission removed.');
    }
}