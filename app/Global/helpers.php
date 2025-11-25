<?php

use Carbon\Carbon;

if (! function_exists('dateUtc')){
    function dateUtc($type = null, $modify = null, $format = 'Y-m-d H:i:s'){

        $carbon = Carbon::now('UTC');

        // Aplica incremento ou decremento se houver, exemplo '+1 day' ou '-2 hours', 'next monday', 'first day of next month', 'last day of december', 'tomorrow 14:00'
        if ($modify) {
            $carbon->modify($modify);
        }

        // Retorna conforme o tipo
        return match ($type) {
            'time' => $carbon->timestamp,
            default => $carbon->format($format),
        };
    }
}

if (! function_exists('dispatchGenericJob')) {
    /**
     * Dispara o GenericJob passando classe, método e parâmetros.
     *
     * @param string $className  Nome completo da classe com namespace (\App\Services\Functions\ErpFunctions::class)
     * @param string $methodName Nome do método a ser chamado
     * @param array $params      Parâmetros para o método (array)
     * @return void
     */
    function dispatchGenericJob(string $className, string $methodName, array $params = [], int $delaySeconds = 0, ?string $queue = 'service')
    {
        $job = new \App\Jobs\GlobalJob($className, $methodName, [$params]);
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);
    
        // globalJob::dispatch($className, $methodName, $params);
    }
}