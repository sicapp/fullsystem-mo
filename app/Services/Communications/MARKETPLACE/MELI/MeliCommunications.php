<?php

namespace App\Services\Communications\MARKETPLACE\MELI;

class MeliCommunications extends Communications
{
    
    public function findItemFromSku($token, $sellerId, $sku){
        //busca por scroll
        $uri = config('services.meli.url') . "/users/$sellerId/items/search?seller_sku={$sku}";        
        return self::get($uri, $token, null, true);
    }
}