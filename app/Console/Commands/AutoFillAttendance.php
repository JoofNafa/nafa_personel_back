<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceAutoFillService;

class AutoFillAttendance extends Command
{
    protected $signature = 'attendance:fill {date?} {shift?}';
    protected $description = 'Remplit automatiquement les présences pour tous les utilisateurs.';

    public function handle()
    {
        $date = $this->argument('date'); // optionnel, sinon aujourd'hui
        $shift = $this->argument('shift'); // optionnel

        $service = new AttendanceAutoFillService();
        $service->fillAttendancesForDate($date, $shift);

        $this->info("Présences remplies pour la date: " . ($date ?? now()->toDateString()) . " et shift: " . ($shift ?? 'tous'));
    }
}
