<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionLogs extends Model
{

    /**
     * Nome da tabela.
     */
    protected $table = 'execution_logs';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'task',
        'details'
    ];

    /**
     * Tipos de dados para conversão automática.
     */
    protected $casts = [
    ];

    /**
     * Controle de timestamps.
     * 
     * Essa tabela possui `created_at` mas não `updated_at`.
     * Mantemos desabilitado para evitar erro de atualização.
     */
    public $timestamps = true;
}
