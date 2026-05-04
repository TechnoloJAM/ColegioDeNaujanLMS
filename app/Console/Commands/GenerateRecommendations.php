<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Course;
use App\Models\Recommendation;
use App\Services\GeminiService;

class GenerateRecommendations extends Command
{
    protected $signature = 'lms:generate-recommendations';
    protected $description = 'Scan grades and use AI to generate study plans for at-risk students';

    public function handle(GeminiService $gemini)
    {
        $courses = Course::where('is_published', true)->with('students.submissions', 'assignments')->get();

        foreach ($courses as $course) {
            foreach ($course->students as $student) {
                
                $totalPoints = 0;
                $earnedPoints = 0;
                $gradesLog = [];
                
                foreach ($course->assignments as $assignment) {
                    $sub = $student->submissions->where('assignment_id', $assignment->id)->first();
                    if ($sub && $sub->grade !== null) {
                        $totalPoints += $assignment->points;
                        $earnedPoints += $sub->grade;
                        $gradesLog[] = "{$assignment->title}: {$sub->grade}/{$assignment->points}";
                    }
                }

                $average = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 100;

                // Threshold: If student drops below 75%
                if ($average < 75 && $average > 0) {
                    $pending = $course->assignments->where('due_date', '>', now())
                        ->whereNotIn('id', $student->submissions->pluck('assignment_id'))
                        ->pluck('title')->join(', ') ?: 'None';

                    $aiPlan = $gemini->generateStudyPlan($student->name, $course->title, implode(', ', $gradesLog), $pending);

                    if ($aiPlan && isset($aiPlan['recommendation'])) {
                        Recommendation::create([
                            'user_id' => $student->id,
                            'category' => 'At-Risk Intervention',
                            'recommendation_text' => $aiPlan['recommendation'],
                            'reasoning' => $aiPlan['reasoning'] ?? 'Based on recent performance trends.'
                        ]);
                    }
                }
            }
        }
        $this->info('AI Recommendations generated successfully.');
    }
}