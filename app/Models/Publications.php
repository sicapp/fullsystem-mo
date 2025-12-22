<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publications extends Model
{

    /**
     * Nome da tabela.
     */
    protected $table = 'publications';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'origin',
        'user_id',
        'item_id',
        'sku',
        'ean',
        'title',
        'prices',
        'variations',
        'params',
        'created_at',
        'updated_at'
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = false;
}
