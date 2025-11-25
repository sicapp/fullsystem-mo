<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GlobalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $className;
    protected string $methodName;
    protected array $params;

    public function __construct(string $className, string $methodName, array $params = [])
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->params = $params;
    }

    public function handle()
    {
        try {

            if (!class_exists($this->className)) {
                $msg = "Classe {$this->className} não existe.";
                Log::error($msg, ['class' => $this->className, 'method' => $this->methodName, 'params' => $this->params]);
                return;
            }

            $instance = app($this->className);

            if (!method_exists($instance, $this->methodName)) {
                $msg = "Método {$this->methodName} não existe na classe {$this->className}.";
                Log::error($msg, ['class' => $this->className, 'method' => $this->methodName, 'params' => $this->params]);
                return;
            }

            Log::channel('jobs')->info('Job iniciado', ['method' => $this->methodName,'class'  => $this->className]);

            // Executa o método com os parâmetros
            return call_user_func_array([$instance, $this->methodName], $this->params);

        } catch (\Throwable $e) {
            Log::error("Erro no GlobalJob: " . $e->getMessage(), [
                'class' => $this->className,
                'method' => $this->methodName,
                'params' => $this->params,
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
