<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateDepartmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insertion des départements par défaut
        $departments = [
            ['name' => 'Customer Service', 'description' => 'Gestion de la relation client'],
            ['name' => 'Compliance', 'description' => 'Conformité et réglementation'],
            ['name' => 'OTM', 'description' => 'Opérations et suivi des transactions'],
            ['name' => 'Finance', 'description' => 'Gestion financière'],
            ['name' => 'IT', 'description' => 'Technologies de l’information'],
            ['name' => 'Commercial', 'description' => 'Ventes et marketing'],
        ];

        DB::table('departments')->insert($departments);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
}
