<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Assignment;
use App\Models\Course;

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
        ]);

        $user = Auth::user();
        $message = $request->input('message');
        
        // 1. IDENTITY & BEHAVIORAL RESTRICTIONS
        $systemPrompt = "You are the AI Assistant for Collegio de Naujan LMS. ";
        $systemPrompt .= "User: {$user->name} (Role: {$user->role}). ";

        if ($user->role === 'student') {
            $systemPrompt .= "Act as a supportive academic tutor. Be encouraging. ";
            $systemPrompt .= "RESTRICTION: Do not provide direct answers to assignments. Give hints and guide the student to the solution. ";
        } elseif ($user->role === 'teacher') {
            $systemPrompt .= "Act as a professional Teaching Assistant. ";
            $systemPrompt .= "RESTRICTION: Help only with lesson planning, grading rubrics, and educational content creation. ";
        } elseif ($user->role === 'admin') {
            $systemPrompt .= "Act as a System Management Assistant. Focus on technical health and user statistics. ";
        }

        // 2. LIVE CONTEXT AWARENESS
        $context = $systemPrompt . "\n\nCURRENT DATA CONTEXT:\n";
        
        if ($user->role === 'student') {
            $enrolled = $user->enrolledCourses()->wherePivot('status', 'approved')->get();
            
            if ($enrolled->isNotEmpty()) {
                $context .= "- Enrolled Courses: " . $enrolled->pluck('title')->implode(', ') . "\n";
                
                $courseIds = $enrolled->pluck('id');
                $pending = Assignment::whereIn('course_id', $courseIds)
                    ->whereDoesntHave('submissions', function($q) use ($user) {
                        // CRITICAL FIX: Changed student_id to user_id
                        $q->where('user_id', $user->id); 
                    })
                    ->orderBy('due_date', 'asc')
                    ->take(5)
                    ->get();
                    
                if ($pending->isNotEmpty()) {
                    $pendingTasks = $pending->map(fn($a) => "{$a->title} (Due: {$a->due_date})")->implode(', ');
                    $context .= "- Pending Tasks: {$pendingTasks}\n";
                }
            } else {
                $context .= "- Status: Not currently attending any approved classes.\n";
            }
        } elseif ($user->role === 'teacher') {
            $myCourses = Course::where('teacher_id', $user->id)->pluck('title')->implode(', ');
            $context .= "- Your Managed Courses: {$myCourses}\n";
        }

        // 3. FINAL SECURITY LAYER
        $context .= "\nINSTRUCTION: Only answer questions related to the Collegio de Naujan LMS and the data provided above. ";
        $context .= "If the user asks for information outside this scope, politely redirect them back to their studies.";

        // 4. SEND TO SERVICE
        $response = $this->gemini->chat($message, $context);

        return response()->json([
            'response' => $response
        ]);
    }
}