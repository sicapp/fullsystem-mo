<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Callback extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'callbacks';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'origin',
        'reference',
        'reference_id',
        'company_id',
        'status',
        'data',
        'data_status',
    ];

    /**
     * Tipos de dados para conversão automática.
     */
    protected $casts = [
        'data' => 'array',
        'data_status' => 'array',
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = true;
}
