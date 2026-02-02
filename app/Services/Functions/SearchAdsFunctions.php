<?php


namespace App\Services\Functions;

use App\Models\Publications;
use App\Services\Communications\ERP\BLING\BlingCommunications;
use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\ProductRelationConnections;
use App\Services\DbConnections\PublicationsConnections;
use Illuminate\Support\Facades\Log;


class SearchAdsFunctions
{
	//FUNÇÃO PARA BUSCA DE ANÚNCIOS DOS SELLERS

    public function __construct(
        protected AuthenticationConnections $authentication_connections,
        protected ProductProviderConnections $product_provider_connections,
        protected ProductRelationConnections $product_relation_connections,
        protected PublicationsConnections $publications_connections,
        protected MeliCommunications $meli_communications,
        protected BlingCommunications $bling_communications
    ) {}

    public function findAds($authId = null){

        Log::channel('process')->info('1- Processo iniciado');
        Log::channel('process')->info('2- $authId:' . $authId);

        //Se $authId == null, então busca de todos os sellers, senão busca do seller específico.
        if($authId){
            // 1 - Busca de um seller específico
            $allAuthentications = $this->authentication_connections->getMinimalDataFromEspecificChannel($authId)->toArray();
        }else{
            // 1 - Busca os canais de vendas em authentications
            $allAuthentications = $this->authentication_connections->getMinimalDataFromChannels()->toArray();
        }

        // 2 - Busca a relação de SKUs e EANs do fornecedor
        //$allProducts = $this->product_provider_connections->getMinimalDataFromProducts()->toArray();
        $delay = 0;
        foreach($allAuthentications as $channels){
            
            // delay para evitar HTTP 429
            $delay += 0.5;
            switch ($channels['code']) {
                case 'MELI':
                    Log::channel('process')->info('3- Code:' . $channels['code'] . '  User_id: ' . $channels['user_id']);
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdMeli', $channels, $delay, 'default');
                    break;

                case 'BLING':
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdBling', $channels, $delay, 'default');
                    break;    

                default:
                    # code...
                    break;
            }
            
        }

    }

    //Mercado Livre
    public function getAdMeli($params){

        $token = $params['token'];
        $sellerId = $params['user_channel_id'];
        $userId = $params['user_id'];

        $scrool = null;
        $allResults = [];
        $limit = 10;
        $count = 0;
        $delay = 0;

        while (true) {

            $tempResult = $this->meli_communications->getPublications(
                $token,
                $sellerId,
                $scrool
            );

            $scrool = $tempResult['data']['scroll_id'] ?? null;

            if (!empty($tempResult['data']['results']) && is_array($tempResult['data']['results'])) {
                $params = [
                    'result' => $tempResult['data']['results'],
                    'token' => $token,
                    'sellerId' => $sellerId,
                    'userId' => $userId
                ];
                $delay += 0.5;
                Log::channel('process')->info('4- Count:' . count($params['result'] ?? []) . ' userId: ' . $params['userId'] . '  sellerId: ' . $params['sellerId']);
                dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getDetailsAdMeli', $params, $delay, 'default');
            }

            $count++;

            if ($count >= $limit || empty($scrool)) {
                break;
            }

        }

    }
    public function getDetailsAdMeli($result){
        $token = $result['token'];
        $itemsIds = implode(',', array_map('trim', array_filter($result['result'])));
        $items = $this->meli_communications->multiGetItems($token, $itemsIds);
        $delay = 0;

        foreach(($items['data'] ?? []) as $item){
            $item = $item['body'];
            $itemId = $item['id'];
            $toStore = [];

            Log::channel('process')->info('5- item_id:' . $item['id'] . ' status: ' . $item['status'] );

            if($item['status'] != 'active'){
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
                    'prices' => json_encode($prices),
                    'variations' => "[]",
                    'params' => json_encode($params),
                    'created_at'  => dateUtc(),
                    'updated_at'  => dateUtc()
                ];

            }
            

            $this->publications_connections->storeOrUpdatePublications($toStore);
            $delay += 0.5;
            Log::channel('process')->info('6- item_id:' . $item['id'] . ' Chegou Verificação' );
            dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'checkPricesMeli', $toStore, $delay, 'default');

        }
    }

    //Bling
    public function getAdBling($params){
        $token = $params['token'];
        $sellerId = $params['user_channel_id'];
        $toStore = [];
        $userId = $params['user_id'];
        $limit = 10;
        $count = 0;
        $page = 1;

        while(true){
            $tempResult = $this->bling_communications->getAllProducts($token, $page);
            $countItems = count($tempResult['data']);

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

            }

            $this->publications_connections->storeOrUpdatePublications($toStore);

            $count++;

            // delay para evitar HTTP 429
            usleep(500000); // 0.5s

            if ($count >= $limit || empty($scrool) || $countItems < 100) {
                break;
            }
        }

    }


    //Funções auxiliares
    public function checkPricesMeli($itemsData){
        $delay = 0;
        foreach($itemsData as $dataItem){
            if($dataItem['status'] == 'active'){
                $sku = $dataItem['sku'];
                $ean = $dataItem['ean'];
                $product = $this->product_provider_connections->findProductBySkuOrEan($sku, $ean);
                Log::channel('process')->info('7- sku:' . $sku . ' ean ' . $ean);
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
                    dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => 0, 'dataCallback' => $dataCallback, 'attempt' => 0], $delay, 'default');
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

}