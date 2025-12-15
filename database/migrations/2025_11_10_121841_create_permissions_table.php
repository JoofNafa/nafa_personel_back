<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // Employé qui demande la permission
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->enum('type', ['messing', 'late', 'early_leave'])->default('messing');
            // Dates de début et fin de la permission
            $table->date('start_date');
            $table->date('end_date');

            // Heures facultatives (nullable pour absences sur toute la journée)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('reason');

            // Statut de la permission
            $table->enum('status', ['pending','approved','rejected'])->default('pending');

            // Manager ou RH qui valide
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
}
