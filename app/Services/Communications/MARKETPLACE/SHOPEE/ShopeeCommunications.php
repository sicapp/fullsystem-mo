<?php


namespace App\Services\Communications\MARKETPLACE\SHOPEE;

class ShopeeCommunications extends Communications
{

// #####################################
// ######   FUNÇÕES EM USO   ######
// #####################################
    //Retorna uma lista paginada de itens, com informações básicas.
    public function getItemList($shopId, $offset = 0, $limit = 100, $itemStatus = 'NORMAL', $token = null){
        /* Exemplo de retorno:
           0 => array:4 [
                "item_id" => 885177829
                "item_status" => "NORMAL"
                "update_time" => 1775120479
                "tag" => array:1 [
                "kit" => false
                ]
            ]
        */
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/get_item_list";

        $extraQuery = [
            'offset'       => $offset,
            'page_size'    => $limit,
            'item_status'    => $itemStatus
        ];

        $uri = self::calcSign($path, $shopId, $token, $extraQuery);

        return self::get($uri, $token);
    }

    public function getItemBaseInfo($shopId, $itemId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/get_item_base_info";

        $extraQuery = [
            'item_id_list' => $itemId,
        ];

        $uri = self::calcSign($path, $shopId, $token, $extraQuery);

        return self::get($uri, $token);
    }

    public function getModelList($shopId, $itemId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/get_model_list";

        $extraQuery = [
            'item_id' => $itemId,
        ];

        $uri = self::calcSign($path, $shopId, $token, $extraQuery);

        return self::get($uri, $token);
    }

    public function updateModelPrice($shopId, $itemId, $modelId, $originalPrice, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/update_price";

        if(!$modelId){
            $params = [
                'item_id' => $itemId,
                'price_list' => [
                    [
                        'model_id' => 0,
                        'original_price' => $originalPrice
                    ]
                ]
            ];
        }else{
            $params = [
                'item_id' => $itemId,
                'price_list' => [
                    [
                        'model_id' => $modelId,
                        'original_price' => $originalPrice
                    ]
                ]
            ];
        }

        $uri = self::calcSign($path, $shopId, $token);

        return self::post($uri, $token, $params);
    }

    public function updateStatusITem($shopId, $itemId, $status, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/update_item";

        $params = [
            'item_id' => $itemId,
            'item_status' => $status
        ];

        $uri = self::calcSign($path, $shopId, $token);

        return self::post($uri, $token, $params);
    }
	
// #####################################
// ######   FUNÇÕES FORA DE USO   ######
// #####################################

    public function getDirectShopProfile($shopId, $token){
        $path = "/api/v2/shop/get_profile";
        $uri = self::calcSign($path, $shopId, $token);
        return self::get($uri, $token);
    }

    //USUARIO - LOJA
    public function getDirectShopInfo($shopId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/shop/get_shop_info";
        $uri = self::calcSign($path, $shopId, $token);
        return self::get($uri, $token);
    }

    public function searchItem($shopId, $offset = 0, $limit = 2, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/search_item";

        $extraQuery = [
            'offset'       => $offset,
            'page_size'    => 2,
            'item_status'    => 'NORMAL'
        ];

        $uri = self::calcSign($path, $shopId, $token, $extraQuery);

        return self::get($uri, $token);
    }

    public function getItemExtraInfo($shopId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/get_item_extra_info";

        $extraQuery = [
            'item_id_list' => 885177652,
        ];

        $uri = self::calcSign($path, $shopId, $token, $extraQuery);

        return self::get($uri, $token);
    }





    public function updateStock($shopId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/update_stock";

        $params = [
            'item_id' => 885177652,
            'stock_list' => [
                [
                    'model_id' => 8501523581,
                    'seller_stock' => [
                        [
                            'stock' => 210
                        ]
                    ]
                ]
            ]
        ];

        $uri = self::calcSign($path, $shopId, $token);

        return self::post($uri, $token, $params);
    }

    public function teste($shopId, $token = null){
        if(!$token){$token = getTokenShopee($shopId);}

        $path = "/api/v2/product/update_stock";

        $params = [
            'item_id' => 885177652,
            'stock_list' => [
                [
                    'model_id' => 8501523581,
                    'seller_stock' => [
                        [
                            'stock' => 209
                        ]
                    ]
                ]
            ]
        ];

        $uri = self::calcSign($path, $shopId, $token);

        return self::post($uri, $token, $params);
    }

    // public function busca_todos_os_sellers_autorizados($shopId, $token = null){
    //     if(!$token){$token = getTokenShopee($shopId);}

    //     $path = "/api/v2/public/get_shops_by_partner";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri, $token);
    // }

    // public function getShopProfile($shopId, $token = null){
    //     if(!$token){$token = getTokenShopee($shopId);}

    //     $path = "/api/v2/shop/get_profile";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri, $token);
    // }

    // public function getDirectWarehouseDetail($shopId, $token = null){
    //     if(!$token){$token = getTokenShopee($shopId);}

    //     $path = "/api/v2/shop/get_warehouse_detail";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }

    // public function getDirectMerchantInfo($shopId, $token = null){
    //     if(!$token){$token = getTokenShopee($shopId);}

    //     $path = "/api/v2/merchant/get_merchant_info";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }


    // #####################################################################################
    // ############################### FUNÇÕES PRIVADAS ####################################
    // #####################################################################################

    private function returnResponse($response, $uri, $params = [], $headers = null)
    {
        $aceptStatus = [200,201,202,203,204];
        if(!in_array($response['status'], $aceptStatus)){
            $returnRes =  json_to_array($response['return']);
            $message = data_get($returnRes, 'error.message');
            $description = data_get($returnRes, 'error.description');

            httpLogRegister($response['status'], __FILE__, $uri, $params, $response);

            return [
                'data'          => null,
                'error'         => $message,
                'error_message' => $description,
                'response'      => $response,
                'http'          => $response['status']
            ];
        }

        if(data_get($response, 'data.data')){
            return data_get($response, 'data');
        }else{
            return ['data' => data_get($response, 'data')];
        }
        
    }

    private function calcSign($path, $shopId, $token, $extraQuery = []){
        $host = config('services.shopee.host');
        $partnerId = config('services.shopee.partner_id');
        $partnerKey = config('services.shopee.partner_key');
        $timest = time();

        // Gera a assinatura
        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timest, $token, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        // Monta query base
        $queryBase = [
            'partner_id'   => $partnerId,
            'timestamp'    => $timest,
            'shop_id'      => $shopId,
            'access_token' => $token,
            'sign'         => $sign
        ];

        $query = array_merge($queryBase, $extraQuery);

        // Gera a URL corretamente
        $uri = $host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $uri;

    }


    // #####################################################################################
    // ############################### MODELOS CHAMADAS ####################################
    // #####################################################################################

    // public function getDirectCategory($shopId, $token){
    //     $path = "/api/v2/product/get_category";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);

    // }

    // public function getDirectAttributeTree($shopId, $categoryId)
    // {
    //     $path = "/api/v2/product/get_attribute_tree";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&category_id_list={$categoryId}");
    // }

    // public function getDirectBandList($shopId, $categoryId)
    // {
    //     $path = "/api/v2/product/get_attribute_tree";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&category_id_list={$categoryId}&offset=0&page_size=100&status=1&language=pt-br");
    // }

    // public function getDirectItemLimit($shopId, $categoryId)
    // {
    //     $path = "/api/v2/product/get_item_limit";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&category_id={$categoryId}");
    // }

    // public function getDirectListItems($shopId, $status){
    //     //NORMAL/BANNED/UNLIST/REVIEWING/SELLER_DELETE/SHOPEE_DELETE
    //     $path = "/api/v2/product/get_item_list";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&offset=0&page_size=10&item_status={$status}&item_status=UNLIST");

    // }

    // public function getDirectItem($shopId = 225968784, $itemIdList = 892607421){
    //     $path = "/api/v2/product/get_item_base_info";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&item_id_list={$itemIdList}&need_tax_info=true&need_complaint_policy=true");
    // }

    // public function getDirectItemExtraInfo($shopId, $itemId)
    // {
    //     $path = "/api/v2/product/get_item_extra_info";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&item_id_list={$itemId}");
    // }

    // public function getDirectVariations($shopId, $itemId)
    // {
    //     $path = "/api/v2/product/get_model_list";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&item_id={$itemId}");
    // }

    // public function getDirectBoostedItem($shopId){
    //     $path = "/api/v2/product/get_boosted_list";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }

    // public function getDirectItemPromotion($shopId, $itemId){
    //     $path = "/api/v2/product/get_item_promotion";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&item_id_list={$itemId}");
    // }

    // public function getDirectSearchItem($shopId, $sku = null, $title = null){

    //     // item_name
    //     // attribute_status
    //     // item_sku
    //     // item_status
    //     // deboost_only

    //     $filter = null;

    //     if($sku != null){
    //         $filter .= "&item_sku={$sku}";
    //     }
    //     if($title != null){
    //         $filter .= "&item_name={$title}";
    //     }

    //     $path = "/api/v2/product/search_item";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . "&offset=0&page_size=10" . $filter);
    // }

    // public function getDirectShopInfo($shopId, $token){
    //     $path = "/api/v2/shop/get_shop_info";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri, $token);
    // }

    // public function getDirectWarehouseDetail($shopId, $token){
    //     $path = "/api/v2/shop/get_warehouse_detail";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }

    // public function getDirectShopNotifications($shopId){
    //     $path = "/api/v2/shop/get_shop_notification";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }

    // public function getDirectAuthorisedResellerBrand($shopId){
    //     $path = "/api/v2/shop/get_authorised_reseller_brand";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri . '&page_no=1&page_size=10');
    // }

    // public function getDirectMerchantInfo($shopId, $token){
    //     $path = "/api/v2/merchant/get_merchant_info";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }

    // public function getDirectOrderList($shopId){
    //     $path = "/api/v2/order/get_order_list";
    //     $uri = self::calcSign($path, $shopId, $token);
    //     return self::get($uri);
    // }


}