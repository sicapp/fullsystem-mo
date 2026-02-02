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
            ['user_id', 'variation_id', 'item_id', 'fs_item'],
            [
                'origin',
                'sku',
                'ean',
                'title',
                'status',
                'fs_item',
                'prices',
                'variations',
                'params',
                'updated_at'
            ]
        );
    }
}