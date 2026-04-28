<?php

namespace App\Services\Communications\MARKETPLACE\MELI;

class MeliCommunications extends Communications
{
    
    public function findItemFromSku($token, $sellerId, $sku){
        //busca por scroll
        $uri = config('services.meli.url') . "/users/$sellerId/items/search?seller_sku={$sku}";        
        return self::get($uri, $token, null, true);
    }

    public function getPublications($token, $sellerId, $scrool){
        //ATENÇÃO, NÃO ALTERAR O LIMITE DESSA CONSULTA, ela deve ser sempre 20.
        $uri = config('services.meli.url') . "/users/$sellerId/items/search?search_type=scan&limit=20&orders=last_updated_asc&status=active&scroll_id={$scrool}";        
        return self::get($uri, $token, null, true);
    }

    public function multiGetItems($token, $itemsIds){
        $uri = config('services.meli.url') . "/items?ids={$itemsIds}";
        return self::get($uri, $token, null, true);
    }

    public function getVariationData($token, $itemId, $varId){
        $uri = config('services.meli.url') . "/items/$itemId/variations/$varId";
        return self::get($uri, $token, null, true);
    }

    public function getItemPrice($token, $resource){
        $uri = config('services.meli.url') . $resource;
        $header = ['show-all-prices' => "true"];
        return self::get($uri, $token, null, true, $header);
    }
    
    public function getItemById($token, $itemId){   
        $uri = config('services.meli.url') . "/items/{$itemId}";
        return self::get($uri, $token, null, true);
    }

    public function setItemPrice($itemId, $price, $token)
    {
        $uri = config('services.meli.url') . "/items/{$itemId}";

        $params = [
            'price' => $price
        ];

        return self::put($uri, $token, $params, true);
    }

    public function pauseItem($itemId, $token)
    {
        $uri = config('services.meli.url') . "/items/{$itemId}";
        return self::put($uri, $token, ['status' => 'paused'], true);
    }

    public function removeWholesalePrice($token, $itemId, $priceId)
    {
        $params = [
            "prices" => [
                ['id' => $priceId]
            ]
        ];

        $uri = config('services.meli.url') . "/items/{$itemId}/prices/standard/quantity";
        return self::post($uri, $token, $params, true);
    }

    public function removeLiquidPrice($token, $itemId){
        $params = ["prices" => []];
        $uri = config('services.meli.url') . "/items/{$itemId}/prices/standard/quantity";
        return self::post($uri, $token, $params, true);
    }
}