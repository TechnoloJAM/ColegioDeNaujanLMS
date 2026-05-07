<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\Announcement;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\GlobalEvent;

class AIChatController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'current_url' => 'nullable|string|max:255',
            'page_title' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $message = $request->input('message');
        $currentUrl = $request->input('current_url', 'Unknown URL');
        $pageTitle = $request->input('page_title', 'Unknown Page');
        
        // 1. CHAT-FRIENDLY PERSONA (The "Goldilocks" Tone)
        $systemPrompt = "You are the AI Co-Pilot for the Collegio de Naujan Learning Management System (CDN LMS). ";
        $systemPrompt .= "You are chatting with {$user->name}, whose role is '{$user->role}'. ";
        $systemPrompt .= "CRITICAL TONE INSTRUCTION: Speak in a natural, conversational, and chat-friendly tone—just like a helpful human assistant. Do not write long essays or massive walls of text. Keep your answers clear, balanced, and easy to read in a chat widget. You may use light formatting (like bold text or simple bullet points) if it helps organize lists naturally.\n\n";

        if ($user->role === 'student') {
            $systemPrompt .= "Act as a supportive academic tutor. Guide the student conversationally. NEVER give direct answers to assignments; instead, give friendly hints.\n";
        } elseif ($user->role === 'teacher') {
            $systemPrompt .= "Act as a helpful Teaching Assistant. Give clear, practical summaries of workload, failing students, and material management.\n";
        } elseif ($user->role === 'admin') {
            $systemPrompt .= "Act as a reliable System Assistant. Provide straightforward, readable summaries of system health, active courses, and pending approvals.\n";
        }

        // 2. LIVE SYSTEM SNAPSHOT (Instant Database Context)
        $context = $systemPrompt . "\n--- LIVE SYSTEM SNAPSHOT (Gathered just now) ---\n";
        $context .= "Use the following real-time data to answer the user's questions accurately. Do not just dump this data into the chat; use it naturally to answer what they specifically asked.\n\n";
        
        $context .= "[Current Screen Context]\n";
        $context .= "- The user is currently looking at: {$pageTitle}\n";
        $context .= "- If they say 'help me with this' or 'what is this', assume they mean the current screen.\n\n";

        // --- STUDENT SNAPSHOT ---
        if ($user->role === 'student') {
            $enrolled = $user->enrolledCourses()->wherePivot('status', 'approved')->get();
            $courseIds = $enrolled->pluck('id');
            $context .= "[Academic Status]\n- Enrolled Courses: " . ($enrolled->pluck('title')->implode(', ') ?: 'None') . "\n";
            
            $pending = Assignment::whereIn('course_id', $courseIds)
                ->whereDoesntHave('submissions', fn($q) => $q->where('user_id', $user->id))
                ->where('due_date', '>', now())
                ->orderBy('due_date', 'asc')->get();
            
            $context .= "- Missing/Pending Tasks (" . $pending->count() . "): " . ($pending->take(5)->map(fn($a) => "{$a->title} (Due: {$a->due_date})")->implode(', ') ?: 'No pending tasks') . "\n";

            $announcements = Announcement::latest()->take(3)->get();
            $context .= "[Campus News]\n- Latest Announcements: " . ($announcements->map(fn($a) => "{$a->title}")->implode(', ') ?: 'None') . "\n";
        } 
        
        // --- TEACHER SNAPSHOT ---
        elseif ($user->role === 'teacher') {
            // Fetch courses with assignments and enrollments to calculate failing students
            $teacherCourses = Course::where('teacher_id', $user->id)
                ->with(['assignments', 'enrollments' => function($q) {
                    $q->where('status', 'approved')->with('user.submissions');
                }])->get();
            
            $courseIds = $teacherCourses->pluck('id');
            $context .= "[Workload Overview]\n- Managed Courses: " . ($teacherCourses->pluck('title')->implode(', ') ?: 'None') . "\n";
            
            $ungradedCount = Submission::whereHas('assignment', fn($q) => $q->whereIn('course_id', $courseIds))
                ->whereNull('grade')->count();
            $context .= "- Submissions waiting to be graded: {$ungradedCount}\n";

            // NEW: FAILING STUDENTS CALCULATION
            $failingList = [];
            foreach($teacherCourses as $c) {
                $coursePoints = $c->assignments->sum('points');
                if ($coursePoints > 0) {
                    foreach($c->enrollments as $enrollment) {
                        if ($student = $enrollment->user) {
                            $earned = $student->submissions->whereIn('assignment_id', $c->assignments->pluck('id'))->sum('grade');
                            $avg = ($earned / $coursePoints) * 100;
                            if ($avg < 75) {
                                $failingList[] = "{$student->name} in '{$c->title}' (Avg: " . round($avg) . "%)";
                            }
                        }
                    }
                }
            }
            $context .= "[Student Performance Alerts]\n- Failing/At-Risk Students (<75%): " . (empty($failingList) ? 'None currently failing.' : implode(', ', $failingList)) . "\n";

            $pendingMaterials = Lesson::whereIn('course_id', $courseIds)->where('approval_status', 'pending')->count();
            $rejectedMaterials = Lesson::whereIn('course_id', $courseIds)->where('approval_status', 'rejected')->get();
            $context .= "[Materials Status]\n- Materials waiting for Admin approval: {$pendingMaterials}\n";
            $context .= "- REJECTED materials needing revision: " . ($rejectedMaterials->pluck('title')->implode(', ') ?: 'None') . "\n";
        } 
        
        // --- ADMIN SNAPSHOT ---
        elseif ($user->role === 'admin') {
            $totalUsers = User::count();
            $activeUsers = User::where('status', 'active')->count();
            $suspendedUsers = User::where('status', 'suspended')->count();
            $totalCourses = Course::count();
            
            $context .= "[System Health]\n- Users: {$totalUsers} Total ({$activeUsers} Active, {$suspendedUsers} Suspended)\n";
            $context .= "- Total Courses: {$totalCourses}\n";

            // NEW: ACTIVE COURSES LIST
            $activeCoursesList = Course::where('is_published', true)->pluck('title');
            $context .= "- Active/Published Courses List: " . ($activeCoursesList->implode(', ') ?: 'No active courses currently.') . "\n";

            $pendingApprovals = Lesson::where('approval_status', 'pending')->with('course:id,title')->get();
            $pendingEnrollments = Enrollment::where('status', 'pending')->count();
            
            $context .= "[Action Items]\n- Course Materials waiting for your approval (" . $pendingApprovals->count() . "): " . ($pendingApprovals->take(5)->map(fn($l) => "'{$l->title}' in {$l->course->title}")->implode(', ') ?: 'None') . "\n";
            $context .= "- Student Enrollments pending approval: {$pendingEnrollments}\n";
        }

        // 3. FINAL SECURITY BOUNDARY
        $context .= "\n--- END SNAPSHOT ---\n";
        $context .= "SECURITY PROTOCOL: Only discuss topics related to education, LMS administration, or the data provided above. If the user asks an unrelated question (e.g., pop culture, politics), politely redirect them to their tasks.";

        // 4. SEND TO GEMINI
        $response = $this->gemini->chat($message, $context);

        return response()->json([
            'response' => $response
        ]);
    }
}