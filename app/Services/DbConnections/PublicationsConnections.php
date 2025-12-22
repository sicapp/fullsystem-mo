<?php

namespace App\Services\DbConnections;

use App\Models\Publications;

class PublicationsConnections extends BaseConnection
{
	/**
     * Classe do Model associado.
     */
    protected string $modelClass = Publications::class;

    public function storeOrUpdatePublications(array $toStore){
        return $this->newQuery()->upsert(
            $toStore,
            ['user_id', 'item_id'],
            [
                'origin',
                'sku',
                'ean',
                'title',
                'status',
                'prices',
                'variations',
                'params',
                'updated_at'
            ]
        );
    }
}