<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentGraded extends Notification
{
    use Queueable;

    public $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Assignment Graded',
            'message' => 'You received a ' . $this->submission->grade . '/' . $this->submission->assignment->points . ' on "' . $this->submission->assignment->title . '".',
            'url' => route('student.courses.show', ['course' => $this->submission->assignment->course_id, 'tab' => 'assignments']),
            'icon' => 'star',
            'color' => 'text-yellow-500',
            // Smart Attributes
            'assignment_id' => $this->submission->assignment_id,
            'urgency' => 'normal'
        ];
    }
}