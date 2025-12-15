<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateUserWeeklyDayOffsTable extends Migration
{
    public function up(): void
    {
        Schema::create('user_weekly_day_offs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // employé
            $table->date('day_off_date'); // jour choisi comme day off

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // RH qui définit

            $table->timestamps();

            // Une contrainte unique pour éviter de créer deux fois le même day off pour le même utilisateur
            $table->unique(['user_id', 'day_off_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_weekly_day_offs');
    }
}
