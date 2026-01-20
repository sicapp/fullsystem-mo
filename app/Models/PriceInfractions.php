<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceInfractions extends Model
{

    /**
     * Nome da tabela.
     */
    protected $table = 'price_infractions';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [ 
        'user_id',
        'seller_id',
        'sku',
        'channel',
        'product_name',
        'minimal_price',
        'announcement_price',
        'price_group_id',
        'price_group_name',
        'punish',
        'item_id',
        'url'
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = true;
}
