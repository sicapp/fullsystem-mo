<?php

namespace App\Services\DbConnections;

use App\Models\ProductRelation;

class ProductRelationConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = ProductRelation::class;
}
