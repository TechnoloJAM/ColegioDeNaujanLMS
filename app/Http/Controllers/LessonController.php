<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\MaterialApproved;
use App\Notifications\MaterialRequiresApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class LessonController extends Controller
{
    public function store(Request $request, Course $course)
    {
        if ($course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);

        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:15360', 
            'available_from' => 'required|date',
            'available_until' => 'required|date|after_or_equal:available_from',
        ]);

        $path = $request->file('file')->store('lessons', 'public');
        $requireApproval = Setting::where('key', 'require_material_approval')->value('value') ?? 'true';
        $initialStatus = ($requireApproval === 'true' && Auth::user()->role !== 'admin') ? 'pending' : 'approved';

        $lesson = $course->lessons()->create([
            'title' => $request->title,
            'attachment_path' => $path, 
            'approval_status' => $initialStatus,
            'available_from' => $request->available_from, 
            'available_until' => $request->available_until, 
        ]);

        // SMART FEATURE: Notify Admins if approval is required
        if ($initialStatus === 'pending') {
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new MaterialRequiresApproval($lesson));
        }

        $msg = $initialStatus === 'pending' ? 'Material uploaded and is waiting for admin approval.' : 'Material uploaded and is instantly active.';
        return back()->with('success', $msg);
    }
    
    public function approve(Lesson $lesson)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $lesson->update(['approval_status' => 'approved']);
        
        // SMART FEATURE: Auto-mark admin notification as read
        Auth::user()->unreadNotifications()
            ->where('type', 'App\Notifications\MaterialRequiresApproval')
            ->where('data', 'like', '%"lesson_id":' . $lesson->id . '%')
            ->get()
            ->markAsRead();

        if ($lesson->course && $lesson->course->teacher) {
            $lesson->course->teacher->notify(new MaterialApproved($lesson));
        }
        
        return back()->with('success', 'Material approved.');
    }

    public function destroy(Lesson $lesson)
    {
        if ($lesson->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $lesson->delete();
        return back()->with('success', 'Lesson deleted.');
    }

    public function archive(Lesson $lesson)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $lesson->update(['available_until' => now(), 'approval_status' => 'approved']);
        return back()->with('success', 'Material archived successfully.');
    }

    public function bulkApprove(Request $request)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $request->validate(['lesson_ids' => 'required|array', 'lesson_ids.*' => 'exists:lessons,id']);
        $lessons = Lesson::with('course.teacher')->whereIn('id', $request->lesson_ids)->get();

        foreach ($lessons as $lesson) {
            $lesson->update(['approval_status' => 'approved']);
            
            // SMART FEATURE: Auto-mark admin notifications as read for each lesson
            Auth::user()->unreadNotifications()
                ->where('type', 'App\Notifications\MaterialRequiresApproval')
                ->where('data', 'like', '%"lesson_id":' . $lesson->id . '%')
                ->get()
                ->markAsRead();

            if ($lesson->course && $lesson->course->teacher) {
                $lesson->course->teacher->notify(new MaterialApproved($lesson));
            }
        }
        return back()->with('success', count($request->lesson_ids) . ' materials have been approved.');
    }

    public function reject(Request $request, Lesson $lesson)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $request->validate(['reason' => 'required|string|max:500']);
        $lesson->update(['approval_status' => 'rejected', 'rejection_note' => $request->reason]);
        
        // SMART FEATURE: Auto-mark admin notification as read (since they rejected it, task is done)
        Auth::user()->unreadNotifications()
            ->where('type', 'App\Notifications\MaterialRequiresApproval')
            ->where('data', 'like', '%"lesson_id":' . $lesson->id . '%')
            ->get()
            ->markAsRead();

        return back()->with('success', 'Material rejected with feedback.');
    }

    public function unarchive(Request $request, Lesson $lesson)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $request->validate(['available_from' => 'required|date', 'available_until' => 'required|date|after_or_equal:available_from']);
        $lesson->update(['available_from' => $request->available_from, 'available_until' => $request->available_until, 'approval_status' => 'approved']);
        return back()->with('success', 'Material unarchived.');
    }

    public function teacherUnarchive(Request $request, Lesson $lesson)
    {
        if ($lesson->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        $request->validate(['available_from' => 'required|date', 'available_until' => 'required|date|after_or_equal:available_from']);
        
        $requireApproval = Setting::where('key', 'require_material_approval')->value('value') ?? 'true';
        $status = ($requireApproval === 'true') ? 'pending' : 'approved';

        $lesson->update(['available_from' => $request->available_from, 'available_until' => $request->available_until, 'approval_status' => $status]);
        
        // SMART FEATURE: Notify Admins if approval is required again
        if ($status === 'pending') {
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new MaterialRequiresApproval($lesson));
        }

        $msg = $status === 'pending' ? 'Unarchive requested. Waiting for admin approval.' : 'Material unarchived successfully.';
        return back()->with('success', $msg);
    }

    public function resubmit(Request $request, Lesson $lesson)
    {
        if ($lesson->course->teacher_id !== Auth::id() && Auth::user()->role !== 'admin') abort(403);
        
        $request->validate([
            'file' => 'required|file|max:15360',
            'available_from' => 'required|date',
            'available_until' => 'required|date|after_or_equal:available_from',
        ]);

        if ($lesson->attachment_path) Storage::disk('public')->delete($lesson->attachment_path);
        $path = $request->file('file')->store('lessons', 'public');

        $requireApproval = Setting::where('key', 'require_material_approval')->value('value') ?? 'true';
        $status = ($requireApproval === 'true' && Auth::user()->role !== 'admin') ? 'pending' : 'approved';

        $lesson->update([
            'attachment_path' => $path,
            'available_from' => $request->available_from,
            'available_until' => $request->available_until,
            'approval_status' => $status,
            'rejection_note' => null 
        ]);
        
        // SMART FEATURE: Notify Admins of the resubmission
        if ($status === 'pending') {
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new MaterialRequiresApproval($lesson));
        }

        $msg = $status === 'pending' ? 'New material uploaded and resubmitted for approval.' : 'New material automatically approved.';
        return back()->with('success', $msg);
    }

    public function update(Request $request, Lesson $lesson)
    {
        if (Auth::user()->role !== 'admin') abort(403);
        $request->validate(['title' => 'required|string|max:255', 'available_from' => 'required|date', 'available_until' => 'required|date|after_or_equal:available_from']);
        $lesson->update($request->only('title', 'available_from', 'available_until'));
        return back()->with('success', 'Material details updated successfully.');
    }
}