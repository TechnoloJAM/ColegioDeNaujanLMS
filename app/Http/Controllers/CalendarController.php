<?php 
namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\GlobalEvent;
use App\Models\Lesson;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller {
    
    public function index() {
        $user = auth()->user();
        $attributes = collect();

        // 1. ADMIN ONLY: Only see the Global Events they manage
        if ($user->role === 'admin') {
            $events = GlobalEvent::where('user_id', $user->id)->get()->map(fn($e) => [
                'key' => 'global-' . $e->id,
                'dates' => $e->start_date,
                'customData' => [
                    'id' => $e->id, 
                    'title' => $e->title, 
                    'type' => 'Institution',
                    'category' => ucfirst($e->type), 
                    'description' => $e->description,
                    'color' => $e->type === 'holiday' ? 'orange' : 'purple', 
                    'is_admin_item' => true
                ]
            ]);
            return Inertia::render('Calendar/Index', ['attributes' => $events]);
        }

        // 2. TEACHER & STUDENT: Role Segregated Content
        // Institutional Events for Everyone else
        $globalEvents = GlobalEvent::whereIn('audience', ['all', $user->role])->get()->map(fn($e) => [
            'key' => 'global-' . $e->id,
            'dates' => $e->start_date,
            'customData' => [
                'id' => $e->id,
                'title' => $e->title, 
                'description' => $e->description,
                'type' => 'Institution', 
                'category' => ucfirst($e->type), 
                'color' => $e->type === 'holiday' ? 'orange' : 'purple',
                'is_admin_item' => false
            ]
        ]);
        $attributes = $attributes->concat($globalEvents);

        // Tasks (Assignments/Quizzes)
        $taskQuery = Assignment::with('course:id,title');
        if ($user->role === 'student') {
            $taskQuery->whereIn('course_id', $user->enrolledCourses()->pluck('course_id'))
                      ->with(['submissions' => fn($q) => $q->where('user_id', $user->id)]);
        } else {
            $taskQuery->whereIn('course_id', $user->courses()->pluck('id'));
        }

        $tasks = $taskQuery->get()->map(function ($t) use ($user) {
            $isDone = ($user->role === 'student' && $t->submissions->isNotEmpty());
            return [
                'key' => 'task-' . $t->id,
                'dates' => $t->due_date,
                'customData' => [
                    'id' => $t->id, 
                    'course_id' => $t->course_id, 
                    'title' => $t->title, // Clean text, no emojis
                    'type' => 'Task', 
                    'category' => ucfirst(str_replace('_', ' ', $t->type)), 
                    'course_title' => $t->course->title,
                    'color' => $isDone ? 'green' : 'blue', 
                    'is_task' => true,
                    'is_done' => $isDone
                ]
            ];
        });
        $attributes = $attributes->concat($tasks);

        // Teacher-only: Material Timers
        if ($user->role === 'teacher') {
            $materials = Lesson::with('course:id,title')->whereIn('course_id', $user->courses()->pluck('id'))->get()->flatMap(function($l) {
                $items = [];
                if($l->available_from) $items[] = ['d' => $l->available_from, 't' => $l->title, 'c' => 'orange', 'action' => 'unlock', 'desc' => 'Auto-Unlocks for Students'];
                if($l->available_until) $items[] = ['d' => $l->available_until, 't' => $l->title, 'c' => 'red', 'action' => 'archive', 'desc' => 'Auto-Archives from Students'];
                
                return collect($items)->map(fn($i) => [
                    'key' => 'mat-'.$l->id.$i['c'], 
                    'dates' => $i['d'],
                    'customData' => [
                        'id' => $l->id,
                        'course_id' => $l->course_id, 
                        'course_title' => $l->course->title,
                        'title' => $i['t'], 
                        'type' => 'Material', 
                        'category' => ucfirst($i['action']),
                        'description' => $i['desc'],
                        'color' => $i['c'], 
                        'is_material' => true,
                        'action' => $i['action']
                    ]
                ]);
            });
            $attributes = $attributes->concat($materials);
        }

        // Student-only: AI Study Blocks
        if ($user->role === 'student') {
            $aiBlocks = Recommendation::where('user_id', $user->id)->get()->map(fn($rec) => [
                'key' => 'ai-' . $rec->id,
                'dates' => $rec->created_at,
                'customData' => [
                    'title' => 'AI Tip: ' . $rec->category, 
                    'description' => $rec->recommendation_text, 
                    'type' => 'AI Study Tip', 
                    'color' => 'indigo'
                ]
            ]);
            $attributes = $attributes->concat($aiBlocks);
        }

        return Inertia::render('Calendar/Index', ['attributes' => $attributes->values()]);
    }

    public function storeEvent(Request $request) {
        if (auth()->user()->role !== 'admin') abort(403);
        $data = $request->validate([
            'title' => 'required|string|max:255', 'type' => 'required|in:academic,holiday,maintenance,event',
            'audience' => 'required|in:all,teacher,student', 'start_date' => 'required|date', 'description' => 'nullable|string'
        ]);
        GlobalEvent::create(array_merge($data, ['user_id' => auth()->id()]));
        return back()->with('success', 'Event added successfully.');
    }

    public function destroyEvent(GlobalEvent $globalEvent) {
        if (auth()->user()->role !== 'admin') abort(403);
        $globalEvent->delete();
        return back()->with('success', 'Event deleted.');
    }
}