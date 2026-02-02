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
        'variation_id',
        'seller_id',
        'sku',
        'ean',
        'title',
        'status',
        'fs_item',
        'prices',
        'variations',
        'params',
        'created_at',
        'updated_at',
        'variation_id',
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = false;
}
