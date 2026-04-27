<?php


namespace App\Services\Functions;

use App\Services\Communications\ERP\BLING\BlingCommunications;
use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\Communications\MARKETPLACE\SHOPEE\ShopeeCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\ProductRelationConnections;
use App\Services\DbConnections\PublicationsConnections;


class SearchAdsFunctions
{
	//FUNÇÃO PARA BUSCA DE ANÚNCIOS DOS SELLERS

    public function __construct(
        protected AuthenticationConnections     $authentication_connections,
        protected ProductProviderConnections    $product_provider_connections,
        protected ProductRelationConnections    $product_relation_connections,
        protected PublicationsConnections       $publications_connections,
        protected MeliCommunications            $meli_communications,
        protected BlingCommunications           $bling_communications,
        protected ShopeeCommunications          $shopee_communications,
    ) {}

    public function findAds($authId = null){

        //Se $authId == null, então busca de todos os sellers, senão busca do seller específico.
        if($authId){
            // 1 - Busca de um seller específico
            $allAuthentications = $this->authentication_connections->getMinimalDataFromEspecificChannel($authId)->toArray();
        }else{
            // 1 - Busca os canais de vendas em authentications
            $allAuthentications = $this->authentication_connections->getMinimalDataFromChannels()->toArray();
        }

        $dataLog = [
            'task' => 'findAds',
            'details' => 'Iniciando busca de anúncios de ' . count($allAuthentications) . ' canais'
        ];
        $idExec = setExecutionLog($dataLog['task'], $dataLog['details']);

        // 2 - Busca a relação de SKUs e EANs do fornecedor
        //$allProducts = $this->product_provider_connections->getMinimalDataFromProducts()->toArray();
        $delayMeli = 0;
        $delayBling = 0;
        $delayShopee = 0;
        $countMeli = 0;
        $countBling = 0;
        $countShopee = 0;

        foreach($allAuthentications as $channels){

            // delay para evitar HTTP 429
            switch ($channels['code']) {
                case 'MELI':
                    $delayMeli += 0.5;
                    $countMeli ++;
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdMeli', $channels, $delayMeli, 'anuMeli');
                    break;

                case 'BLING':
                    $delayBling += 1;
                    $countBling ++;
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdBling', $channels, $delayBling, 'anuBling');
                    break; 
                    
                case 'SHOPEE':
                    $delayShopee += 0.5;
                    $countShopee ++;
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdShopee', $channels, $delayShopee, 'anuShopee');
                    break; 

                default:
                    # code...
                    break;
            }
            
        }

        $result = 'Busca de anúncios concluída. Resultados: Meli:' . $countMeli . ', BLING:' . $countBling . ', SHOPEE:' . $countShopee;
        updateExecutionLog($idExec, $result);

    }

    //Mercado Livre
    public function getAdMeli(array $params): void
    {
        $token = $params['token'];
        $sellerId = $params['user_channel_id'];
        $userId = $params['user_id'];

        $scrollId = null;
        $lastScrollId = null;
        $count = 0;
        $jobDelay = 0;

        $refList = $this->getDataProducts();

        $idExec = setExecutionLog(__FUNCTION__, 'Iniciado job para buscar anúncios do Mercado Livre');
        $execs = 0;
        $faileds = 0;
        $locals = null;

        while (true) {
            $execs++;
            $response = $this->meli_communications->getPublications(
                $token,
                $sellerId,
                $scrollId
            );

            if (!empty($response['error'])) {
                logger()->warning('Falha ao buscar publicacoes no Mercado Livre', [
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                    'scroll_id' => $scrollId,
                    'response' => $response,
                ]);
                $faileds++;
                $locals .= __LINE__ . ", (" . $execs . ")";
                continue;
            }

            $data = $response['data'] ?? null;
            $results = $data['results'] ?? [];
            $nextScrollId = $data['scroll_id'] ?? null;

            if (!is_array($results)) {
                logger()->warning('Resposta inesperada ao buscar publicacoes no Mercado Livre', [
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                    'scroll_id' => $scrollId,
                    'response' => $response,
                ]);
                $faileds++;
                $locals .= __LINE__ . ", (" . $execs . ")";
                continue;
            }

            if (!empty($results)) {
                $jobParams = [
                    'result' => $results,
                    'token' => $token,
                    'sellerId' => $sellerId,
                    'userId' => $userId,
                    'refList' => $refList,
                ];

                $jobDelay += 0.5;
                dispatchGenericJob(
                    \App\Services\Functions\SearchAdsFunctions::class,
                    'getDetailsAdMeli',
                    $jobParams,
                    $jobDelay,
                    'anuMeli'
                );
            }else{
                $faileds++;
                $locals .= __LINE__ . ", (" . $execs . ")";
            }

            $count++;

            if (empty($nextScrollId)) {
                break;
            }

            if ($nextScrollId === $lastScrollId) {
                logger()->warning('scroll_id repetido na busca de publicacoes do Mercado Livre', [
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                    'scroll_id' => $nextScrollId,
                ]);
                $faileds++;
                $locals .= __LINE__ . ", (" . $execs . ")";
                continue;
            }

            if ($count >= 1000) {
                logger()->warning('Limite de iteracoes atingido na busca de publicacoes do Mercado Livre', [
                    'seller_id' => $sellerId,
                    'user_id' => $userId,
                    'count' => $count,
                ]);
                $faileds++;
                $locals .= __LINE__ . ", (" . $execs . ")";
                continue;
            }

            $lastScrollId = $scrollId;
            $scrollId = $nextScrollId;

            usleep(100000); // 0.3s para reduzir risco de HTTP 429
        }

        $result = 'Verificação concluída. Execuções: ' . $execs . ', Falhas: ' . $faileds . ', Locais: ' . $locals . ', Seller: ' . $sellerId;
        updateExecutionLog($idExec, $result);
    }

    public function getDetailsAdMeli($result){
        $token = $result['token'];
        $itemsIds = implode(',', array_map('trim', array_filter($result['result'])));
        $items = $this->meli_communications->multiGetItems($token, $itemsIds);
        $delay = 0;
        $refList = $result['refList'];

        $idExec = setExecutionLog(__FUNCTION__, 'Iniciado grupos de '  . count($items['data'] ?? []) . ' anuncios Meli.');

        $execs = 0;

        foreach(($items['data'] ?? []) as $item){
            $item = $item['body'];
            $itemId = $item['id'];
            $toStore = [];
            $execs ++;

            if($item['status'] != 'active'){
                //TODO verificar se o item está pausado ou excluido. Se estiver excluido, remover da tabela.
                continue;
            }

            $params = [
                'family_name' => $item['family_name'] ?? null,
                'user_product_id' => $item['user_product_id'] ?? null,
                'official_store_id' => $item['official_store_id'] ?? null,
                'available_quantity' => $item['available_quantity'] ?? null,
                'sold_quantity' => $item['sold_quantity'] ?? null,
                'listing_type_id' => $item['listing_type_id'] ?? null,
                'start_time' => $item['start_time'] ?? null,
                'permalink' => $item['permalink'] ?? null,
                'thumbnail' => $item['thumbnail'] ?? null,
                'tags' => $item['tags'] ?? [],
            ];
            $prices = [
                'price' => $item['price'] ?? null,
                'base_price' => $item['base_price'] ?? null,
                'original_price' => $item['original_price'] ?? null,
            ];


            //verificando se o item tem variações ou se é um item simples.
            if($item['variations'] != []){
                //tem variações

                foreach($item['variations'] as $variation){
                    $varId = $variation['id'];
                    //buscar os dados da variação no mercado livre:
                    $varMeli = $this->meli_communications->getVariationData($token, $itemId, $varId);
                    $variationData = $varMeli['data'] ?? null;

                    if(!$variationData){
                        //se nao achou a variação, pula ela.
                        continue;
                    }

                    $attribs = $this->getAttrigutes($variationData['attributes'] ?? []);

                    $variations = [
                        'id' => $variation['id'],
                        'price' => $variation['price'],
                        'attribute_name' => $variation['attribute_combinations'][0]['name'],
                        'attribute_value' => $variation['attribute_combinations'][0]['value_name'],
                        'available_quantity' => $variation['available_quantity'],
                        'sold_quantity' => $variation['sold_quantity'],
                        'user_product_id' => $variation['user_product_id']
                    ];

                    $fsItem = (in_array($attribs['sku'], $refList) || in_array($attribs['ean'], $refList));

                    $toStore[] = [
                        'origin' => "MELI",
                        'user_id' => $result['userId'],
                        'item_id' => $itemId,
                        'variation_id' => $varId,
                        'seller_id' => $item['seller_id'],
                        'sku' => $attribs['sku'],
                        'ean' => $attribs['ean'],
                        'title' => $item['title'] . " " . $variation['attribute_combinations'][0]['value_name'],
                        'status' => $item['status'],
                        'fs_item' => $fsItem,
                        'prices' => json_encode($prices),
                        'variations' => json_encode($variations),
                        'params' => json_encode($params),
                        'created_at'  => dateUtc(),
                        'updated_at'  => dateUtc()
                    ];

                }
            
            }else{
                //não tem variações
                $attribs = $this->getAttrigutes($item['attributes'] ?? []);

                $fsItem = (in_array($attribs['sku'], $refList) || in_array($attribs['ean'], $refList));

                $toStore[] = [
                    'origin' => "MELI",
                    'user_id' => $result['userId'],
                    'item_id' => $item['id'],
                    'variation_id' => 0,
                    'seller_id' => $item['seller_id'],
                    'sku' => $attribs['sku'],
                    'ean' => $attribs['ean'],
                    'title' => $item['title'],
                    'status' => $item['status'],
                    'fs_item' => $fsItem,
                    'prices' => json_encode($prices),
                    'variations' => "[]",
                    'params' => json_encode($params),
                    'created_at'  => dateUtc(),
                    'updated_at'  => dateUtc()
                ];

            }

            $this->publications_connections->storeOrUpdatePublications($toStore);
            $delay += 0.5;
        }

        updateExecutionLog($idExec, 'Finalizado grupos de 20 anuncios Meli.  Executados: ' . $execs);
    }

    //Bling
    public function getAdBling($params){

        $token = $params['token'];
        $sellerId = $params['user_channel_id'];
        $toStore = [];
        $userId = $params['user_id'];
        $count = 1;
        $page = 1;
        $totalCount = 0;

        $while = true;

        $finale = null;

        $idExec = setExecutionLog(__FUNCTION__, 'Iniciado job para buscar produtos no Bling.  UserId: ' . $userId);

        while($while){
            $tempResult = $this->bling_communications->getAllProducts($token, $page);
            $countItems = count($tempResult['data'] ?? []);

            if($countItems > 0){

                //processar os itens
                foreach(($tempResult['data'] ?? []) as $dataItem){

                    if($dataItem['formato'] == 'V'){
                        continue;
                        // Se for produto PAI não registramos, registraremos apenas as variações.
                    }

                    //se tiver ID de produto pai, então é uma variação:
                    if($dataItem['idProdutoPai'] ?? false){
                        //é variação
                        $itemId = $dataItem['idProdutoPai'];
                        $varId = $dataItem['id'];
                    }else{
                        //não é variação
                        $itemId = $dataItem['id'];
                        $varId = 0;
                    }

                    $params = [
                        'idProdutoPai' => $dataItem['idProdutoPai'] ?? null,
                        'stock' => $dataItem['estoque'] ?? null,
                        'type' => $dataItem['tipo'] ?? null,
                        'format' => $dataItem['formato'] ?? null,
                        'imageURL' => $dataItem['imagemURL'] ?? null,
                    ];

                    $toStore[] = [
                        'origin' => "BLING",
                        'user_id' => $userId,
                        'item_id' => $itemId,
                        'variation_id' => $varId,
                        'seller_id' => $sellerId,
                        'sku' => $dataItem['codigo'],
                        'ean' => null,
                        'title' => $dataItem['nome'],
                        'status' => $dataItem['situacao'],
                        'prices' => json_encode(['price' => $dataItem['preco']]),
                        'variations' => json_encode([]),
                        'params' => json_encode(['idProdutoPai' => $dataItem['idProdutoPai'] ?? null]),
                        'created_at'  => dateUtc(),
                        'updated_at'  => dateUtc()
                    ];

                    $totalCount++;

                }

                $this->publications_connections->storeOrUpdatePublications($toStore);

            }else{
                //sai do while
                $while = false;
                $finale .= __LINE__ . ' ';
            }

            $page++;

            $count++;

            // delay para evitar HTTP 429
            usleep(500000); // 0.5s

            //Trava de segurança para evitar loop infinito e limita a busca de 10.000 produtos
            if($count > 100){
                $while = false;
                $finale .= __LINE__ . ' ';
            }
        }

        updateExecutionLog($idExec, 'Finalizado busca de produtos no Bling.  Executados: ' . $totalCount . ' Paginas: ' . $page . ' Saida: ' . $finale);

    }

    //shopee
    public function getAdShopee($params){
        // Implementação para obter anúncios do Shopee
        $sellerId = $params['user_channel_id'];
        $userId = $params['user_id'];

        $offset = 0;
        $limit = 20;
        $count = 0;
        $delay = 0;
        $itemStatus = 'NORMAL';
        $while = true;

        $refList = $this->getDataProducts();
        
        $idExec = setExecutionLog(__FUNCTION__, 'Iniciado job para buscar anúncios na Shopee');

        $totalAds = 0;
        $countJobs = 0;
        $faileds = null;

        while ($while) {
            //Busca a lista de anuncios na Shopee
            $tempResult = $this->shopee_communications->getItemList($sellerId, $offset, $limit, $itemStatus);
            $dataItem   = data_get($tempResult, 'data.response.item');

            if ($dataItem) {
                $countJobs ++;
                $totalAds += count($dataItem ?? []);
                
                $paramsItem = [
                    'result' => $dataItem,
                    'sellerId' => $sellerId,
                    'userId' => $userId,
                    'refList' => $refList,
                ];

                $delay += 0.5;

                dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getDetailsAdShopee', $paramsItem, $delay, 'anuShopee');
                
                //atualiza o offset
                $offset = data_get($tempResult, 'data.response.next_offset');
            }else{
                //sair do while
                $while = false;
                $faileds .= "," . __LINE__;
            }

            $count++;

            if (empty($offset)) {
                //sair do while
                $while = false;
            }

            //Trava de segurança para evitar loop infinito e limita a busca de 20.000 anúncios
            if($count > 1000){
                $while = false;
                $faileds .= "," . __LINE__;
            }

            // delay para evitar HTTP 429
            usleep(500000); // 0.5s

        }

        $result = 'Total de anúncios: ' . $totalAds . ', Jobs criados: ' . $countJobs . ', Falhas: ' . $faileds;
        updateExecutionLog($idExec, $result);

    }
    public function getDetailsAdShopee($result)
    {
        $itemsGroup = $result['result'];
        $sellerId = $result['sellerId'];
        $userId = $result['userId'];
        $refList = $result['refList'];
        $modelData = null;
        $toStore = [];

        $idExec = setExecutionLog(__FUNCTION__, 'Iniciados detalhes anuncios.  Total: ' . count($itemsGroup));

        $countExec = 0;
        foreach($itemsGroup as $item){
            
            $itemsDataShopee = $this->shopee_communications->getItemBaseInfo($sellerId, $item['item_id']);
            $itemData = data_get($itemsDataShopee, 'data.response.item_list.0');
            $hasModel = data_get($itemData, 'has_model', false);
            
            if($hasModel){
                //produto com variações
                $modelDataShopee = $this->shopee_communications->getModelList($sellerId, $item['item_id']);
                $modelData = data_get($modelDataShopee, 'data.response');
                $tierVariation = $modelData['tier_variation'] ?? [];

                foreach($modelData['model'] as $model){
                    $sku = data_get($model, 'model_sku');
                    $gtin = data_get($model, 'gtin_code');

                    $fsItem = (in_array($sku, $refList) || in_array($gtin, $refList));
                    $prices = [];
                    $prices = [
                        'price' => $model['current_price'] ?? null,
                        'base_price' => $model['original_price'] ?? null,
                        'original_price' => $model['original_price'] ?? null,
                    ];

                    $variations = [];
                    $variations = [
                        'id' => $model['model_id'],
                        'price' => $model['price_info'][0]['current_price'] ?? null,
                        'attribute_name' => $model['model_name'],
                        'attribute_value' => null,
                        'available_quantity' => $model['stock_info_v2']['summary_info']['total_available_stock'],
                        'sold_quantity' => null,
                        'user_product_id' => null
                    ];

                    //pegando o tier_variation
                    $varName = null;
                    foreach($model['tier_index'] as $tier){
                        foreach($tierVariation as $variation){
                            if(!$varName){
                                $varName = $variation['name'] . ':' . $variation['option_list'][$tier]['option'];
                            }else{
                                $varName .= '|' . $variation['name'] . ':' . $variation['option_list'][$tier]['option'];
                            }
                            
                        }
                    }
                    
                    $params = [];
                    $params = [
                        'category_id' =>  $itemData['category_id'] ?? null,
                        'image' =>  $itemData['image']['image_url_list'] ?? null,
                        'weight' =>  $itemData['weight'] ?? null,
                        'dimension' =>  $itemData['dimension'] ?? null,
                        'logistic_info' =>  $itemData['logistic_info'] ?? null,
                        'wholesales' =>  $itemData['wholesales'] ?? null,
                        'condition' =>  $itemData['condition'] ?? null,
                        'has_promotion' =>  $itemData['has_promotion'] ?? null,
                        'brand' =>  $itemData['brand'] ?? null,
                        'deboost' =>  $itemData['deboost'] ?? null,
                        'is_fulfillment_by_shopee' =>  $itemData['is_fulfillment_by_shopee'] ?? null,
                        'tag' =>  $itemData['tag'] ?? null
                    ];

                    $toStore[] = [
                        'origin' => "SHOPEE",
                        'user_id' => $userId,
                        'item_id' => $itemData['item_id'],
                        'variation_id' => $model['model_id'],
                        'seller_id' => $sellerId,
                        'sku' => $model['model_sku'] ?? null,
                        'ean' => $model['gtin_code'] ?? null,
                        'title' => $itemData['item_name'] . ' - ' . $varName,
                        'status' => $itemData['item_status'] ?? null,
                        'fs_item' => $fsItem,
                        'prices' => json_encode($prices),
                        'variations' => json_encode($variations),
                        'params' => json_encode($params),
                        'created_at'  => dateUtc(),
                        'updated_at'  => dateUtc()
                    ];

                }

                $countExec++;

            }else{
                //produto sem variações
                $fsItem = (in_array($itemData['item_sku'] ?? null, $refList) || in_array($itemData['gtin_code'] ?? null, $refList));

                $prices = [];
                $prices = [
                    'price' => $itemData['price_info'][0]['current_price'] ?? null,
                    'base_price' => $itemData['price_info'][0]['original_price'] ?? null,
                    'original_price' => $itemData['price_info'][0]['original_price'] ?? null,
                ];

                $params = [];
                $params = [
                    'category_id' =>  $itemData['category_id'] ?? null,
                    'image' =>  $itemData['image']['image_url_list'] ?? null,
                    'weight' =>  $itemData['weight'] ?? null,
                    'dimension' =>  $itemData['dimension'] ?? null,
                    'logistic_info' =>  $itemData['logistic_info'] ?? null,
                    'wholesales' =>  $itemData['wholesales'] ?? null,
                    'condition' =>  $itemData['condition'] ?? null,
                    'has_promotion' =>  $itemData['has_promotion'] ?? null,
                    'brand' =>  $itemData['brand'] ?? null,
                    'deboost' =>  $itemData['deboost'] ?? null,
                    'is_fulfillment_by_shopee' =>  $itemData['is_fulfillment_by_shopee'] ?? null,
                    'tag' =>  $itemData['tag'] ?? null
                ];

                $toStore[] = [
                    'origin' => "SHOPEE",
                    'user_id' => $userId,
                    'item_id' => $itemData['item_id'],
                    'variation_id' => 0,
                    'seller_id' => $sellerId,
                    'sku' => $itemData['item_sku'],
                    'ean' => $itemData['gtin_code'],
                    'title' => $itemData['item_name'],
                    'status' => $itemData['item_status'] ?? null,
                    'fs_item' => $fsItem,
                    'prices' => json_encode($prices),
                    'variations' => '[]',
                    'params' => json_encode($params),
                    'created_at'  => dateUtc(),
                    'updated_at'  => dateUtc()
                ];

                $countExec++;

            }
            usleep(500000); // 0.5s Delay para evitar http 429
        }

        $this->publications_connections->storeOrUpdatePublications($toStore);
        $result = 'Total executado: ' . $countExec;
        updateExecutionLog($idExec, $result);

    }


    //Funções auxiliares
    public function checkPricesMeli($itemsData){
        $delay = 0;
        foreach($itemsData as $dataItem){
            if($dataItem['status'] == 'active'){
                $sku = $dataItem['sku'];
                $ean = $dataItem['ean'];
                $product = $this->product_provider_connections->findProductBySkuOrEan($sku, $ean);

                if($product){
                    //gerando o job de verificação de preços:
                    $itemId = $dataItem['item_id'];
                    $dataCallback = [
                        'origin' => $dataItem['origin'],
                        'reference' => "items_prices",
                        'reference_id' => "/items/{ $itemId }/prices",
                        'company_id' => $dataItem['seller_id'],
                        'status' => 0,
                        'data' => null,
                        'data_status' => null,
                        'created_at' => dateUtc(),
                        'updated_at' => dateUtc()
                    ];

                    $delay += 0.5;
                    dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], $delay, 'anuMeli');
                }
            }
        }
    }

    private function getAttrigutes($data){
        foreach(($data ?? []) as $attributes){
            if($attributes['id'] == 'GTIN'){
                $ean = $attributes['value_name'];
            }
            if($attributes['id'] == 'SELLER_SKU'){
                $sku = $attributes['value_name'];
            }
        }

        return [
            'ean' => $ean ?? null,
            'sku' => $sku ?? null,
        ];
    }

    private function getDataProducts(){
        $products = $this->product_provider_connections->getAll();

        $refist = [];

        foreach($products as $product){
            if($product['sku']){
                $refist[] = $product['sku'];
            }
            if($product['ean']){
                $refist[] = $product['ean'];
            }
        }
        
        return $refist;
    }

}