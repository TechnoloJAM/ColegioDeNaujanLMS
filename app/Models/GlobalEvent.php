<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GlobalEvent extends Model {
    protected $fillable = ['user_id', 'title', 'description', 'type', 'audience', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'datetime', 'end_date' => 'datetime'];
    public function creator() { return $this->belongsTo(User::class, 'user_id'); }
}