<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $groqApiKey;
    protected $openRouterApiKey; 
    
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->groqApiKey = env('GROQ_API_KEY');
        $this->openRouterApiKey = env('OPENROUTER_API_KEY'); 
    }

    // =========================================================
    // 1. THE 4-TIER AI WATERFALL
    // =========================================================
    public function chat(string $message, string $context = '')
    {
        $finalPrompt = "CONTEXT: {$context}\n\nQUESTION: {$message}";
        $hitRateLimit = false;

        // --- TIER 1: GOOGLE GEMINI ---
        if (!empty($this->apiKey)) {
            $googleAttempts = [
                ['url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent', 'name' => 'Gemini 3 Flash'],
                ['url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', 'name' => 'Gemini 2.5 Flash'],
            ];

            foreach ($googleAttempts as $attempt) {
                try {
                    $response = Http::timeout(15)->withOptions(['verify' => false])
                        ->post("{$attempt['url']}?key={$this->apiKey}", [
                            "contents" => [["parts" => [["text" => $finalPrompt]]]]
                        ]);

                    if ($response->successful()) {
                        return $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? "I couldn't process that.";
                    }

                    if ($response->status() === 429) {
                        $hitRateLimit = true;
                        Log::warning("Gemini Rate Limit Hit. Falling back to Tier 2 (Groq).");
                        break; 
                    }
                } catch (\Exception $e) {
                    Log::error("Gemini Connection Error: " . $e->getMessage());
                }
            }
        }

        // --- TIER 2: GROQ (1,000 Free Requests/Day) ---
        if (!empty($this->groqApiKey)) {
            $groqResponse = $this->fallbackToGroq($finalPrompt);
            if ($groqResponse) return $groqResponse;
        }

        // --- TIER 3: OPENROUTER MULTI-MODEL (50 Free Requests/Day) ---
        if (!empty($this->openRouterApiKey)) {
            $openRouterResponse = $this->fallbackToOpenRouter($finalPrompt);
            if ($openRouterResponse) return $openRouterResponse;
        }

        // --- TIER 4: THE FRIENDLY COOLDOWN ---
        if ($hitRateLimit) {
            return "Our AI assistant is experiencing high traffic right now! ⚡\n\nPlease try asking again in a few minutes, or use the quick buttons below to view your tasks and grades directly from the database.";
        }

        return "AI System is currently undergoing maintenance. Please try again later!";
    }

    // =========================================================
    // TIER 2 LOGIC (GROQ / LLAMA 3)
    // =========================================================
    private function fallbackToGroq($prompt)
    {
        try {
            $response = Http::timeout(15)->withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("https://api.groq.com/openai/v1/chat/completions", [
                    'model' => 'llama3-8b-8192', // Blistering fast Llama 3 model
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error("Groq Fallback Error: " . $e->getMessage());
        }
        return null;
    }

    // =========================================================
    // TIER 3 LOGIC (OPENROUTER MULTI-MODEL LOOP)
    // =========================================================
    private function fallbackToOpenRouter($prompt)
    {
        // If one free model fails, it instantly tries the next one!
        $freeModels = [
            'meta-llama/llama-3.1-8b-instruct:free',
            'google/gemma-4-31b-it:free', 
            'cohere/north-mini-code:free'
        ];

        foreach ($freeModels as $model) {
            try {
                $response = Http::timeout(15)->withOptions(['verify' => false])
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->openRouterApiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("https://openrouter.ai/api/v1/chat/completions", [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                        'temperature' => 0.7,
                    ]);

                if ($response->successful()) {
                    Log::info("OpenRouter succeeded using: " . $model);
                    return $response->json()['choices'][0]['message']['content'] ?? null;
                }
            } catch (\Exception $e) {
                Log::error("OpenRouter Error on {$model}: " . $e->getMessage());
            }
        }
        return null;
    }

    // =========================================================
    // BACKGROUND AI FUNCTIONS (JSON Data for Dashboards)
    // =========================================================
    
    public function recommendCourses($student, $availableCourses, $enrolledHistory)
    {
        if (empty($this->apiKey) || $availableCourses->isEmpty()) return null;
        
        $historyList = $enrolledHistory->pluck('title')->join(', ');
        $courseList = $availableCourses->map(fn($c) => "ID:{$c->id} | {$c->title} ({$c->difficulty_level})")->join("\n");
        
        $prompt = "Act as an academic advisor. Student History: [{$historyList}] Available Courses: {$courseList} Task: Recommend exactly 3 courses. Return ONLY JSON array: [{\"id\": 1, \"reason\": \"Short reason\"}]";
        
        return $this->callGeminiForJson($prompt);
    }

    public function recommendStudyPlan($courseTitle, $currentGrade, $assignments, $lessons)
    {
        if (empty($this->apiKey)) return null;

        $assignmentList = collect($assignments)->map(fn($a) => "ID:{$a['id']} | Task: {$a['title']}")->join(", ");
        $lessonList = collect($lessons)->map(fn($l) => "ID:{$l['id']} | Material: {$l['title']}")->join(", ");

        $prompt = "
            Student is failing '{$courseTitle}' with a grade of {$currentGrade}%.
            Available Pending Assignments: [{$assignmentList}]
            Available Learning Materials: [{$lessonList}]
            Task: Pick exactly 2 items. Return ONLY raw JSON array: [{\"type\": \"assignment\" or \"lesson\", \"id\": 1, \"tip\": \"Short study tip\"}]
        ";

        return $this->callGeminiForJson($prompt);
    }

    private function callGeminiForJson($prompt)
    {
        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]);
            
            if ($response->failed()) return null;
            
            $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = preg_replace('/^```json|```$/m', '', $text); 
            return json_decode($text, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}