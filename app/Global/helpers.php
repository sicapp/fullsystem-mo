<?php

use Carbon\Carbon;
use App\Services\DbConnections\CallbackConnections;
use App\Services\DbConnections\MessageConnections;

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

// Função que atualiza os callbacks recebidos com o resultado de sua execução
if (!function_exists('updateCallback')) {
    function updateCallback(int $callbackId, int $status, array $dataError): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $dataError['reference'] = [
            'file' => $trace['file'] ?? 'desconhecido',
            'line' => $trace['line'] ?? 'desconhecida'
        ];

        $data = [
            'status' => $status,
            'updated_at' => dateUtc(),
            'data_status' => json_encode(($dataError ?? []))
        ];

        $callbagConnections = new CallbackConnections;
        $callbagConnections->updateCallback($callbackId, $data);

    }
}

//Função para transformar json em array
if (!function_exists('json_to_array')) {
    function json_to_array($value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->toArray();
        }

        if (!is_string($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $default;
    }
}

// Salvar mensagem para o usuário
if (!function_exists('createSystemMessage')) {
    function createSystemMessage(
        string $messageId,
        int $userId,
        string $title,
        string $messageText,
        ?int $alert = 0
    ): void {

        /** @var MessageConnections $messageConnections */
        $messageConnections = app(MessageConnections::class);

        $params = [
            'message_id' => $messageId,
            'user_id'    => $userId,
            'title'      => $title,
            'message'    => $messageText,
            'alert'      => $alert,
            'created_at' => dateUtc(),
        ];

        $messageConnections->insertOrIgnore($params, null);
    }
}

if (! function_exists('brMoney')) {
    function brMoney($v)
    {
        return $v === null || $v === '' ? '' : 'R$ ' . number_format((float) $v, 2, ',', '.');
    }
}