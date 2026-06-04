<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['teacher_id', 'enrollment_code', 'title', 'description', 'difficulty_level', 'thumbnail', 'is_published', 'is_admin_hidden'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($course) {
            if ($course->isForceDeleting()) return;
            $course->lessons()->delete();
            $course->assignments()->delete();
            $course->announcements()->delete();
        });

        static::forceDeleted(function ($course) {
            $course->lessons()->withTrashed()->chunk(100, function ($lessons) {
                $lessons->each->forceDelete();
            });
            $course->assignments()->withTrashed()->chunk(100, function ($assignments) {
                $assignments->each->forceDelete();
            });
            $course->announcements()->withTrashed()->chunk(100, function ($announcements) {
                $announcements->each->forceDelete();
            });
            $course->enrollments()->delete();
            
            if ($course->thumbnail) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $course->thumbnail));
            }
        });
    }

    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function lessons() { return $this->hasMany(Lesson::class); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function announcements() { return $this->hasMany(Announcement::class); }
    
    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_id', 'user_id')->withPivot('status')->withTimestamps();
    }
}