<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Submission extends Model
{
    use HasFactory; 

    protected $fillable = ['assignment_id', 'user_id', 'file_paths', 'text_content', 'grade', 'feedback'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($submission) {
            if ($submission->file_paths) {
                $paths = json_decode($submission->file_paths, true);
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        });
    }

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function student() { return $this->belongsTo(User::class, 'user_id'); }
}