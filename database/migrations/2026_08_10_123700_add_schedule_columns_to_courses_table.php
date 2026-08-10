<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Changed from json() to text() to support your database version
            $table->text('days')->nullable()->after('difficulty_level'); 
            $table->time('start_time')->nullable()->after('days');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('room')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['days', 'start_time', 'end_time', 'room']);
        });
    }
};