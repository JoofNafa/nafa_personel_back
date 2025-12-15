<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('date'); // date de présence
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('minutes_late')->default(0); // retard du jour
            $table->integer('total_minutes_late')->default(0); // cumul
            $table->enum('status', ['present','absent','day_off','on_leave', 'permission'])->default('present');
            $table->boolean('early_leave')->default(false);
            $table->string('scan_method')->nullable(); // QR
            $table->timestamps();

            $table->unique(['user_id','date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
}
