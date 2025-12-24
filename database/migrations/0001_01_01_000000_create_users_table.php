<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('pin');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->enum('role', ['employee','manager','rh','admin', 'vigile'])->default('employee');

            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');// Équipe matin / soir
            $table->integer('leave_balance')->default(0);
            $table->boolean('must_change_password')->default(true);// Solde congés
            $table->boolean('must_change_pin')->default(true);// Solde congés
            $table->boolean('works_weekend')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        $morningShift = DB::table('shifts')->where('type', 'morning')->first();
        $eveningShift = DB::table('shifts')->where('type', 'evening')->first();

        // Insertion des utilisateurs par défaut
        DB::table('users')->insert([
            [
                'first_name' => 'Admin',
                'last_name' => 'NAFA',
                'email' => 'admin@nafa.com',
                'phone' => '76 622 93 46',
                'password' => Hash::make('NAFA2025'),
                'pin' => Hash::make('2025'),
                'role' => 'admin',
                'shift_id' => $morningShift?->id,
                'leave_balance' => 0,
                'must_change_password' => true,
                'must_change_pin' => true,
                'works_weekend' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'RH',
                'last_name' => 'NAFA',
                'email' => 'rh@nafa.com',
                'phone' => '77 526 35 63',
                'password' => Hash::make('NAFA2025'),
                'pin' => Hash::make('2025'),
                'role' => 'rh',
                'shift_id' => $morningShift?->id,
                'leave_balance' => 0,
                'must_change_password' => true,
                'must_change_pin' => true,
                'works_weekend' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'first_name' => 'Nafa',
                'last_name' => 'NAFA',
                'email' => 'rha@nafa.com',
                'phone' => '77 526 35 64',
                'password' => Hash::make('NAFA2025'),
                'pin' => Hash::make('2025'),
                'role' => 'vigile',
                'shift_id' => $morningShift?->id,
                'leave_balance' => 0,
                'must_change_password' => true,
                'must_change_pin' => true,
                'works_weekend' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
