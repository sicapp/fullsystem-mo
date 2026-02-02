<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TimingController extends Controller
{
    public function pacemaker()
    {

        $calling = [];

        $lock = Cache::lock('pacemaker_global_lock', 30);
        if (! $lock->get()) {
            return response()->json(['status' => 'locked']); 
        }

        try {
            $minuto    = (int) date('i');
            $hora      = (int) date('H');
            $diaMes    = (int) date('d');
            $diaSemana = (int) date('w');

            $minuteKey = 'pacemaker_minute_' . date('YmdHi');
            if (Cache::has($minuteKey)) {
                return response()->json(['status' => 'already_executed']);
            }
            Cache::put($minuteKey, true, now()->addMinutes(1));

            // INTERVALOS CURTOS
            $this->Interval_1_minut();
            $calling[] = 'Interval_1_minut';

            if ($minuto % 2 === 0) {
                $this->Interval_2_minuts();
                $calling[] = 'Interval_2_minuts';
            }

            if ($minuto % 5 === 0) {
                $this->Interval_5_minuts();
                $calling[] = 'Interval_5_minuts';
            }

            if ($minuto % 10 === 0) {
                $this->Interval_10_minuts();
                $calling[] = 'Interval_10_minuts';
            }

            if ($minuto % 15 === 0) {
                $this->Interval_15_minuts();
                $calling[] = 'Interval_15_minuts';
            }

            if ($minuto % 30 === 0) {
                $this->Interval_30_minuts();
                $calling[] = 'Interval_30_minuts';
            }

            if ($minuto === 0) {
                $this->Interval_1_hour();
                $calling[] = 'Interval_1_hour';
            }

            // EXECUÇÕES DIÁRIAS E SEMANAIS
            $this->handleDailyTasks($diaSemana, $diaMes, $calling);

            return response()->json([
                'status' => 'executed',
                'minute' => $minuto,
                'hour' => $hora,
                'day' => $diaMes,
                'weekday' => $diaSemana,
                'time' => now()->toDateTimeString(),
                'calling' => $calling,
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function handleDailyTasks(int $diaSemana, int $diaMes, array &$calling)
    {
        $dailyKey = 'pacemaker_daily_' . date('Ymd');
        if (Cache::has($dailyKey)) {
            return;
        }
        Cache::put($dailyKey, true, now()->addHours(24));

        $this->Interval_1_day();
        $calling[] = 'Interval_1_day';

        switch ($diaSemana) {
            case 1:
                $this->Interval_Monday();
                $calling[] = 'Interval_Monday';
                break;
            case 2:
                $this->Interval_Tuesday();
                $calling[] = 'Interval_Tuesday';
                break;
            case 3:
                $this->Interval_Wednesday();
                $calling[] = 'Interval_Wednesday';
                break;
            case 4:
                $this->Interval_Thursday();
                $calling[] = 'Interval_Thursday';
                break;
            case 5:
                $this->Interval_Friday();
                $calling[] = 'Interval_Friday';
                break;
            case 6:
                $this->Interval_Saturday();
                $calling[] = 'Interval_Saturday';
                break;
            case 0:
                $this->Interval_Sunday();
                $calling[] = 'Interval_Sunday';
                break;
        }

        if ($diaMes === 1) {
            $this->Interval_1_month();
            $calling[] = 'Interval_1_month';
        }
    }

    // INTERVALOS CURTOS
    private function Interval_1_minut()
    { 
        //
    }
    private function Interval_2_minuts()
    { 
        //
    }
    private function Interval_5_minuts()
    { 
        //
    }
    private function Interval_10_minuts()
    { 
        //
    }
    private function Interval_15_minuts()
    { 
        //
    }
    private function Interval_30_minuts()
    { 
        //
    }
    private function Interval_1_hour()
    {
        $now = Carbon::now('America/Sao_Paulo');

        if($now->hour === 1 && $now->minute === 0)  //Executa a 1 da manhã
        {   
            dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'findAds', [], 0, 'default');
        }

        if($now->hour === 4 && $now->minute === 0)  //Executa as 4 da manhã
        {   
            dispatchGenericJob(\App\Services\Functions\MonitoringFunction::class, 'getItemToMonitoring', [], 0, 'default');
        }

        if($now->hour === 16 && $now->minute === 0)  //Executa as 16 da manhã
        {   
            dispatchGenericJob(\App\Services\Functions\MonitoringFunction::class, 'getItemToMonitoring', [], 0, 'default');
        }
        
    }

    // EXECUÇÕES DIÁRIAS E SEMANAIS
    private function Interval_1_day()
    { /* Executa todo dia */  
    }
    private function Interval_Monday()
    { /* Segunda */
    }
    private function Interval_Tuesday()
    { /* Terça */
    }
    private function Interval_Wednesday()
    { /* Quarta */
    }
    private function Interval_Thursday()
    { /* Quinta */
    }
    private function Interval_Friday()
    { /* Sexta */
    }
    private function Interval_Saturday()
    { /* Sábado */
    }
    private function Interval_Sunday()
    { /* Domingo */
    }

    // EXECUÇÃO MENSAL
    private function Interval_1_month()
    { /* Dia 1 de cada mês */
    }
}
