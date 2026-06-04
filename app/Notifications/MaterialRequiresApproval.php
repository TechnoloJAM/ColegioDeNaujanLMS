<?php

namespace App\Notifications;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaterialRequiresApproval extends Notification
{
    use Queueable;

    public $lesson;

    public function __construct(Lesson $lesson)
    {
        $this->lesson = $lesson;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Pending Material Approval',
            'message' => "{$this->lesson->course->teacher->name} uploaded '{$this->lesson->title}' for review.",
            
            // FIX: Changed 'admin.materials.index' to 'admin.materials' to perfectly match web.php
            'url' => route('admin.materials'), 
            
            'icon' => 'shield-exclamation',
            'color' => 'text-orange-500',
            // Smart Attributes
            'lesson_id' => $this->lesson->id,
            'urgency' => 'high'
        ];
    }
}