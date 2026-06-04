<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentDeadlineApproaching extends Notification
{
    use Queueable;

    public $assignment;

    public function __construct(Assignment $assignment)
    {
        $this->assignment = $assignment;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Deadline Approaching!',
            'message' => "Your assignment '{$this->assignment->title}' in {$this->assignment->course->title} is due in 24 hours.",
            'url' => route('student.courses.show', ['course' => $this->assignment->course_id, 'tab' => 'assignments']),
            'icon' => 'clock',
            'color' => 'text-red-500',
            // Smart Attributes
            'assignment_id' => $this->assignment->id,
            'urgency' => 'high' 
        ];
    }
}