<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateShiftsTable extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label')->unique();
            $table->enum('type', ['morning', 'evening',])->default('morning');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        // 🔹 Insérer les shifts par défaut si non existants
        if (DB::table('shifts')->count() === 0) {
            DB::table('shifts')->insert([
                [
                    'name' => '8H-17H',
                    'label' => 'Shift 08:00 - 17:00',
                    'type' => 'morning',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => '9H-17H',
                    'label' => 'Shift 09:00 - 17:00',
                    'type' => 'morning',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => '8H-16H',
                    'label' => 'Shift 08:00 - 16:00',
                    'type' => 'morning',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => '17H-00H',
                    'label' => 'Shift 17:00 - 00:00',
                    'type' => 'evening',
                    'start_time' => '17:00:00',
                    'end_time' => '00:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => '16H30-23H30',
                    'label' => 'Shift 16:30 - 23:30',
                    'type' => 'evening',
                    'start_time' => '16:30:00',
                    'end_time' => '23:30:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
}
