<?php


namespace App\Services\Functions;

use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\PriceInfractionsConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\PublicationsConnections;
use App\Services\DbConnections\UserConnections;
use Carbon\Carbon;

class InboundPricesFunctions
{
	public function __construct(
        protected AuthenticationConnections     $authentication_connections,
        protected MeliCommunications            $meli_communications,
        protected PublicationsConnections       $publications_connections,
        protected ProductProviderConnections    $product_provider_connections,
        protected UserConnections               $user_connections,
        protected SearchAdsFunctions            $search_ads_functions,
        protected PriceInfractionsConnections   $price_infractions_connections
    ) {}

    public function pricesInbound($dataRequest){
    
        $idCallBack = $dataRequest['idCallBack'];
        $sellerId = $dataRequest['dataCallback']['company_id'];
        $resource = $dataRequest['dataCallback']['reference_id'];
        $attempt = $dataRequest['attempt'];

        //processo iniciado:
        updateCallback($idCallBack, 1, ['Iniciado processo']);

        //consultar dados do seller:
        $sellerData = $this->authentication_connections->findBy('user_channel_id', $sellerId)->toArray();
        //vendo se está ativo e o token está válido.
        $timestamp = $sellerData['token_valid_at'];
        $target = Carbon::createFromTimestamp($timestamp);
        $now    = now(); // Carbon::now() em UTC se APP_TIMEZONE=UTC

        $minutesLeft = $now->diffInMinutes($target, false);

        if($minutesLeft <= 0){
            //o token está vencido, então aborta o processo
            updateCallback($idCallBack, 4, ['Token do seller esta vencido!']);
            return false;
        }

        if($minutesLeft <= 3){
            //o token está prestes a vencer, então reprograma o processo.
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataRequest['dataCallback'], 'attempt' => 0], 300, 'default');
            updateCallback($idCallBack, 3, ['Token do seller esta prestes a vencer, reprogramado processo!']);
        }

        //consultando o usuário do sistema
        $userData = $this->user_connections->getById($sellerData['user_id']);
        $userPriceGroupId = $userData['price_group']??null;

        $userId = $userData['id'];

        $token = $sellerData['token'];
        $itemId = str_replace(['items', 'prices', '/'], "", $resource);

        //Consultando o item no Mercado livre
        $itemData = $this->meli_communications->getItemById($token, $itemId);

        if(!$itemData['success']){
            //Não conseguimos consultar os preços, então aborta o processo
            updateCallback($idCallBack, 3, ['Erro ao consultar o anuncio' => $itemData]);
            return false;
        }

        $item = $itemData['data'];
        $sellerId = $item['seller_id'];
        $itemName = $item['title'];
        $permalink = $item['permalink'];

        //consultar o resource:
        $priceData = $this->meli_communications->getItemPrice($token, $resource);

        if(!$priceData['success']){
            //Não conseguimos consultar os preços, então aborta o processo
            updateCallback($idCallBack, 3, ['Erro ao consultar os preços' => $priceData]);
            return false;
        }

        $salePrice = 9999999999;
        //passou, então vamos verificar o menor preço praticado pelo anúncio:
        foreach($priceData['data']['prices'] as $value) {
            if($value['amount'] < $salePrice){
                $salePrice = $value['amount'];
            }
        }

        //vamos buscar a publicação no nosso banco:  07330305AA
        $publications = $this->publications_connections->findAllBy('item_id', $itemId);

        if(count($publications) > 0){
            $groupName = null;
            foreach($publications as $pubData){

                $sku = $pubData['sku'];
                $ean = $pubData['ean'];

                $productData = null;
                $productData = $this->product_provider_connections->findBy('sku', $sku);

                if(!$productData){
                    //Não encontrou o produto cadastrado com esse SKU, então procuramos pelo ean:
                    $productData = $this->product_provider_connections->findBy('ean', $ean);
                }

                if(!$productData){
                    updateCallback($idCallBack, 2, ['Attempt' => $attempt, 'message' => 'Produto não existe no sistema!']);
                    return false;
                }

                $pricesGroups = json_to_array($productData['prices_group'] ?? []);

                $minimalPriceToUse = 0;
                
                //Se o usuário não tem pricegroup ou o produto não tem pricegroup, consultar o valor padrão.
                if(!$userPriceGroupId || !$pricesGroups){
                    $minimalPriceToUse = $productData['price_minimal'];
                }else{
                    foreach($pricesGroups as $pGroup){
                        if($pGroup['id'] == $userPriceGroupId){
                            $minimalPriceToUse = data_get($pGroup, 'values.mercadolivre.minimum_selling', 0);
                            $groupName = $pGroup['name'];
                            break;
                        }
                    }
                }
            }
        }else{
            //Não temos os dados desse produto, então vamos cadastrar;

            //Evitando loop infinito
            if($attempt > 0){
                //já tentou uma vez então aborta o processo e registra o erro
                updateCallback($idCallBack, 3, ['Attempt' => $attempt, 'message' => 'Nao conseguimos salvar a publicacao!']);
                return false;
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
                        'user_id' => $userId,
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
                        'user_id' => $userId,
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

            //reprograma o processo
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataRequest['dataCallback'], 'attempt' => 1], 300, 'default');
            return false;

        }

        // Agora verificamos se o anúncio está abaixo do preço mínimo    $sellerId
        if ($salePrice < $minimalPriceToUse){
            //Está abaixo do mínimo.
            $dataInfraction = [
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'sku' => $sku,
                'channel' => 'Mercado Livre',
                'product_name' => $itemName,
                'minimal_price' => $minimalPriceToUse,
                'announcement_price' => $salePrice,
                'price_group_id' => $userPriceGroupId,
                'price_group_name' => $groupName,
                'punish' => null,
                'item_id' => $itemId,
                'url' => $permalink,
                'created_at' => dateUtc()
            ];

            $pauseAdd = true;

            //1 - Tentar ajustar o preço
            $updatePrice = $this->meli_communications->setItemPrice($itemId, $minimalPriceToUse, $token);
            $updateResult = data_get($updatePrice, 'data.price', 0);
            if($updateResult > 0){
                $pauseAdd = true;
                $dataInfraction['punish'] = 'O preço foi atualizado';
            }
            
            //2 - Pausar anúncio
            if($pauseAdd){
                //$paused = $this->meli_communications->pauseItem($itemId, $token);
                // $resultPause = data_get($paused, 'data.status', false);
                // if($resultPause == 'paused'){
                //     $dataInfraction['punish'] = 'O anúncio foi pausado';
                // }
                //TODO: REATIVAR PARA PAUSAR
            }

            //3 - Registrar a infração.
            $infraction = $this->price_infractions_connections->insertGetId($dataInfraction);

            //4 - Enviar mensagem ao usuário.
            $punish = $dataInfraction['punish'];
            if($punish){
                $messageText = "O anúncio { $itemId } estava abaixo do preço mínimo. Resultado: { $punish }";
            }else{
                $messageText = "O anúncio { $itemId } está abaixo do preço mínimo. Atualize o preço para no mínimo R$ " . brMoney($minimalPriceToUse);
            }
            
            $messageId = "Price_Infraction_" . $itemId . date('y_m_d');

            createSystemMessage($messageId, $userId, $messageText, 1);

        }

    }


    //Funções auxiliares

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

    // private function getAttributeValue(array $attributes, string $id): ?string
    // {
    //     foreach ($attributes as $attribute) {
    //         if (($attribute['id'] ?? null) === $id) {
    //             return $attribute['value_name'] ?? null;
    //         }
    //     }

    //     return null;
    // }

}