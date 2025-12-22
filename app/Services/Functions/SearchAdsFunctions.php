<?php


namespace App\Services\Functions;

use App\Models\Publications;
use App\Services\Communications\ERP\BLING\BlingCommunications;
use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\ProductRelationConnections;
use App\Services\DbConnections\PublicationsConnections;

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

    public function findAds(){
        // 1 - Busca os canais de vendas em authentications
        $allAuthentications = $this->authentication_connections->getMinimalDataFromChannels()->toArray();

        // 2 - Busca a relação de SKUs e EANs do fornecedor
        //$allProducts = $this->product_provider_connections->getMinimalDataFromProducts()->toArray();

        foreach($allAuthentications as $channels){

            switch ($channels['code']) {
                case 'MELI':
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdMeli', $channels, 0, 'default');
                    break;

                case 'BLING':
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdBling', $channels, 0, 'default');
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
                dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getDetailsAdMeli', $params, 0, 'default');
            }

            $count++;

            // delay para evitar HTTP 429
            usleep(500000); // 0.4s

            if ($count >= $limit || empty($scrool)) {
                break;
            }

        }

    }
    public function getDetailsAdMeli($result){
        $itemsIds = implode(',', array_map('trim', array_filter($result['result'])));
        $items = $this->meli_communications->multiGetItems($result['token'], $itemsIds);

        
        foreach(($items['data'] ?? []) as $item){
            $item = $item['body'];

            $sku = null;
            $ean = null;

            foreach(($item['attributes'] ?? []) as $attributes){
                if($attributes['id'] == 'GTIN'){
                    $ean = $attributes['value_name'];
                }
                if($attributes['id'] == 'SELLER_SKU'){
                    $sku = $attributes['value_name'];
                }
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

            $variations = [];
            if($item['variations'] != []){
                foreach($item['variations'] as $variation){
                    $variation[] = [
                        'id' => $variation['id'],
                        'price' => $variation['price'],
                        'attribute_name' => $variation['attribute_combinations'][0]['name'],
                        'attribute_value' => $variation['attribute_combinations'][0]['value_name'],
                        'available_quantity' => $variation['available_quantity'],
                        'sold_quantity' => $variation['sold_quantity'],
                        'user_product_id' => $variation['user_product_id']
                    ];
                }
            }

            $toStore[] = [
                'origin' => "MELI",
                'user_id' => $result['userId'],
                'item_id' => $item['id'],
                'seller_id' => $item['seller_id'],
                'sku' => $sku,
                'ean' => $ean,
                'title' => $item['title'],
                'status' => $item['status'],
                'prices' => json_encode($prices),
                'variations' => json_encode($variations),
                'params' => json_encode($params),
                'created_at'  => dateUtc(),
                'updated_at'  => dateUtc()
            ];

            $this->publications_connections->storeOrUpdatePublications($toStore);

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
                    'item_id' => $dataItem['id'],
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

}