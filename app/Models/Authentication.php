<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Authentication extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'authentication';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'user_id',
        'user_channel_id',
        'user_channel_name',
        'user_channel_document',
        'user_channel_email',
        'user_channel_data_contrato',
        'code',
        'group',
        'name',
        'resource_id',
        'active',
        'token',
        'refresh_token',
        'token_valid_at',
        'type',
        'dataJson',
        'created_at',
        'updated_at'
    ];

    /**
     * Tipos de dados para conversão automática.
     */
    protected $casts = [
        'active' => 'boolean',
        'dataJson' => 'array',
        'user_erp_data_contrato' => 'date',
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = true;
}
