<?php


namespace App\Services\Functions;

use App\Services\Communications\ERP\BLING\BlingCommunications;
use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\ProductRelationConnections;

class SearchAdsFunctions
{
	//FUNÇÃO PARA BUSCA DE ANÚNCIOS DOS SELLERS

    public function __construct(
        protected AuthenticationConnections $authentication_connections,
        protected ProductProviderConnections $product_provider_connections,
        protected ProductRelationConnections $product_relation_connections,
        protected MeliCommunications $meli_communications,
        protected BlingCommunications $bling_communications
    ) {}

    public function findAds(){
        // 1 - Busca os canais de vendas em authentications
        $allAuthentications = $this->authentication_connections->getMinimalDataFromChannels()->toArray();

        // 2 - Busca a relação de SKUs e EANs do fornecedor
        $allProducts = $this->product_provider_connections->getMinimalDataFromProducts()->toArray();

        foreach($allAuthentications as $channels){

            $params = [
                'channels' => $channels,
                'allProducts' => $allProducts
            ];

            switch ($channels['code']) {
                case 'MELI':
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdMeli', $params, 0, 'default');
                    break;

                case 'BLING':
                    dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'getAdBling', $params, 0, 'default');
                    break;    

                default:
                    # code...
                    break;
            }
        }

    }

    public function getAdMeli($params){

        $token = $params['channels']['token'];
        $sellerId = $params['channels']['user_channel_id'];
        $toAdd = [];

        foreach($params['allProducts'] as $product){
            $productId = $product['id'];
            $sku = $product['sku'];
            $tempResult = $this->meli_communications->findItemFromSku($token, $sellerId, $sku); 
            
            if(count(($tempResult['data']['results'] ?? [])) > 0){
                foreach($tempResult['data']['results'] as $result){
                    $toAdd[] = [
                        'user_id' => $params['channels']['user_id'],
                        'seller_id' => $sellerId,
                        'product_id' => $productId,
                        'origin_relats' => $params['channels']['code'],
                        'resource' => $result,
                        'direction' => 'in',
                        'created_at' => dateUtc()
                    ];
                }
            }
        }

        $this->saveRelations($toAdd);
    }

    public function getAdBling($params){
        $token = $params['channels']['token'];
        $sellerId = $params['channels']['user_channel_id'];
        $toAdd = [];

        foreach($params['allProducts'] as $product){
            $productId = $product['id'];
            $sku = $product['sku'];
            $tempResult = $this->bling_communications->getProductBySku($token, $sku);

            foreach(($tempResult['data'] ?? []) as $result){
                $toAdd[] = [
                    'user_id' => $params['channels']['user_id'],
                    'seller_id' => $sellerId,
                    'product_id' => $productId,
                    'origin_relats' => $params['channels']['code'],
                    'resource' => $result['id'],
                    'direction' => 'in',
                    'created_at' => dateUtc()
                ];
            }
        }

        $this->saveRelations($toAdd);

    }

    private function saveRelations($toAdd){
        $this->product_relation_connections->insertOrIgnore($toAdd);
    }


    // 3 - Busca os anúncios dos marketplaces e erps

    // 4 - Relaciona os anúncios (sku anuncio x sku fornecedor)

    // 5 - Salva o relacionamento
}