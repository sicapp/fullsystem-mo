<?php


namespace App\Services\Functions;

use App\Services\DbConnections\PublicationsConnections;
use Illuminate\Support\Facades\Log;

class MonitoringFunction
{
	
    public function __construct(
       protected PublicationsConnections $publicationsConnections
    ){}

    public function getItemToMonitoring(){

        $publications = $this->publicationsConnections->getItemsToMonitoring();

        $idExec = setExecutionLog(__FUNCTION__, 'Iniciado Monitoramento de ' . count($publications) . ' anuncios.');

        $itemsMeli      = 0;
        $itemsShopee    = 0;

        $delayMeli      = 0;
        $delayShopee    = 0;

        foreach($publications as $dataItem){

            if($dataItem['origin'] === 'MELI'){
                $itemsMeli++;

                $itemId = $dataItem['item_id'];

                $dataCallback = [
                    'origin' => 'MELI',
                    'reference' => "items_prices",
                    'reference_id' => "/items/{$itemId}/prices",
                    'company_id' => $dataItem['seller_id'],
                    'status' => 0,
                    'data' => null,
                    'data_status' => null,
                    'created_at' => dateUtc(),
                    'updated_at' => dateUtc()
                ];

                dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], $delayMeli, 'anuMeli');

                $delayMeli += 2;

            } elseif($dataItem['origin'] === 'SHOPEE') {

                $itemsShopee++;

                $dataCallback = [
                    'origin' => 'SHOPEE',
                    'reference' => 22,
                    'reference_id' => (int)$dataItem['item_id'] . ':' . (int)$dataItem['variation_id'],
                    'company_id' => (int)$dataItem['seller_id'],
                    'status' => 0,
                    'data' => json_encode($dataItem),
                    'data_status' => null,
                    'created_at' => dateUtc(),
                    'updated_at' => dateUtc()
                ];

                dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInboundShopee', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], $delayShopee, 'anuShopee');

                $delayShopee += 2;
            }

        
        }

        updateExecutionLog($idExec, 'Jobs Criados. Meli: ' . $itemsMeli . ' | Shopee: ' . $itemsShopee);
    }
}