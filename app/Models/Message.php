<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'messages';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'message_id',
        'user_id',
        'message',
        'open_at',
        'read_at',
        'alert',
        'created_at',
    ];

    /**
     * Tipos de dados para conversão automática.
     */
    protected $casts = [
        'open_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Controle de timestamps.
     * 
     * Essa tabela possui `created_at` mas não `updated_at`.
     * Mantemos desabilitado para evitar erro de atualização.
     */
    public $timestamps = false;
}
