<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assignment;
use App\Notifications\AssignmentDeadlineApproaching;

class SendDeadlineReminders extends Command
{
    protected $signature = 'lms:send-reminders';
    protected $description = 'Send notifications for assignments due in 24 hours';

    public function handle()
    {
        // Find assignments due strictly between 23 to 24 hours from now
        $upcomingAssignments = Assignment::whereBetween('due_date', [now()->addHours(23), now()->addHours(24)])
            ->with('course.students')
            ->get();

        foreach ($upcomingAssignments as $assignment) {
            // Get students enrolled who HAVE NOT submitted yet
            $studentsToRemind = $assignment->course->students()
                ->whereDoesntHave('submissions', function ($query) use ($assignment) {
                    $query->where('assignment_id', $assignment->id);
                })->get();

            foreach ($studentsToRemind as $student) {
                $student->notify(new AssignmentDeadlineApproaching($assignment));
            }
        }
        
        $this->info('Deadline reminders dispatched successfully.');
    }
}