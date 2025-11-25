<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRelation extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'products_relations';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'user_id',
        'seller_id',
        'product_id',
        'origin_relats',
        'resource',
        'direction'
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = true;
}
