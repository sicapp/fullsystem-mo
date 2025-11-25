<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductProvider extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     */
    protected $table = 'products_providers';

    /**
     * Campos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'provider_id',
        'provider_name',
        'status',
        'sku',
        'ean',
        'unit',
        'father_id',
        'variation_id',
        'name',
        'full_price',
        'cost_price',
        'picking_cost',
        'band',
        'model',
        'warranty',
        'stock',
        'url_docs',
        'url_media',
        'net_weight',
        'gross_weight',
        'height_pack',
        'depth_pack',
        'width_pack',
        'origin',
        'ncm',
        'cest',
        'additional_info',
        'manufacturing_time',
        'template',
        'user_groups',
        'price_group',
    ];

    /**
     * Tipos de dados para conversão automática.
     */
    protected $casts = [
        'variation_id' => 'array',
        'price_group' => 'array',
        'full_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'picking_cost' => 'decimal:2',
        'net_weight' => 'float',
        'gross_weight' => 'float',
        'height_pack' => 'float',
        'depth_pack' => 'float',
        'width_pack' => 'float',
    ];

    /**
     * Controle de timestamps (created_at e updated_at).
     */
    public $timestamps = true;
}
