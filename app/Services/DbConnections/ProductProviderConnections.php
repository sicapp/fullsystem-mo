<?php

namespace App\Services\DbConnections;

use App\Models\ProductProvider;

class ProductProviderConnections extends BaseConnection
{
    /**
     * Classe do Model associado.
     */
    protected string $modelClass = ProductProvider::class;

    public function getMinimalDataFromProducts(){
        return $this->newQuery()
            ->select('id','sku','ean')
            ->get();
    }

    public function findProductBySkuOrEan($sku, $ean){
        return $this->newQuery()
            ->where([
                ['sku', $sku],
                ['ean', $ean]
            ])
            ->first();
    }

}
