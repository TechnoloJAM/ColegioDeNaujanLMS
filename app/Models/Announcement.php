<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['course_id', 'user_id', 'title', 'content', 'video_link'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($announcement) {
            if ($announcement->isForceDeleting()) return;
            // FIX: Ghost Notification Cleanup
            DB::table('notifications')
                ->where('type', 'App\Notifications\NewAnnouncementPosted')
                ->where('data', 'like', '%"announcement_id":' . $announcement->id . '%')
                ->delete();
        });

        static::forceDeleted(function ($announcement) {
            $announcement->comments()->withTrashed()->chunk(100, function ($comments) {
                $comments->each->forceDelete();
            });
        });
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function comments() { return $this->hasMany(Comment::class); }
}