<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['course_id', 'title', 'attachment_path', 'available_from', 'available_until', 'approval_status', 'rejection_reason', 'is_archived'];
    protected $casts = ['available_from' => 'datetime', 'available_until' => 'datetime', 'is_archived' => 'boolean'];

    protected static function boot()
    {
        parent::boot();

        static::forceDeleted(function ($lesson) {
            if ($lesson->attachment_path) {
                Storage::disk('public')->delete($lesson->attachment_path);
            }
        });
    }

    public function course() { return $this->belongsTo(Course::class); }
}