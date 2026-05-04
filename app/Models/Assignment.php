<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['course_id', 'title', 'description', 'type', 'points', 'due_date', 'closing_date', 'attachment_paths'];
    protected $casts = ['due_date' => 'datetime', 'closing_date' => 'datetime'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($assignment) {
            if ($assignment->isForceDeleting()) return;
            
            DB::table('notifications')
                ->where('type', 'App\Notifications\NewAssignmentPosted')
                ->where('data', 'like', '%"assignment_id":' . $assignment->id . '%')
                ->delete();
        });

        static::forceDeleted(function ($assignment) {
            $assignment->submissions()->chunk(100, function ($submissions) {
                $submissions->each->delete();
            });

            if ($assignment->attachment_paths) {
                $paths = json_decode($assignment->attachment_paths, true);
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        });
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function submissions() { return $this->hasMany(Submission::class); }
}