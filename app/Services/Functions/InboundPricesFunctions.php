<?php


namespace App\Services\Functions;

use App\Services\Communications\MARKETPLACE\MELI\MeliCommunications;
use App\Services\Communications\MARKETPLACE\SHOPEE\ShopeeCommunications;
use App\Services\DbConnections\AuthenticationConnections;
use App\Services\DbConnections\PriceInfractionsConnections;
use App\Services\DbConnections\ProductProviderConnections;
use App\Services\DbConnections\PublicationsConnections;
use App\Services\DbConnections\UserConnections;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InboundPricesFunctions
{
	public function __construct(
        protected AuthenticationConnections     $authentication_connections,
        protected MeliCommunications            $meli_communications,
        protected PublicationsConnections       $publications_connections,
        protected ProductProviderConnections    $product_provider_connections,
        protected UserConnections               $user_connections,
        protected SearchAdsFunctions            $search_ads_functions,
        protected PriceInfractionsConnections   $price_infractions_connections,
        protected ShopeeCommunications          $shopee_communications,
    ) {}

    // MERCADO LIVRE
    public function pricesInbound($dataRequest){
  
        $idCallBack = $dataRequest['idCallBack'];
        $sellerId = $dataRequest['dataCallback']['company_id'];
        $resource = $dataRequest['dataCallback']['reference_id'];
        $attempt = $dataRequest['attempt'];

        if($idCallBack > 0){
            //processo iniciado:
            updateCallback($idCallBack, 1, ['Iniciado processo']);
        }
        //consultar dados do seller:
        $sellerData = $this->authentication_connections->findBy('user_channel_id', $sellerId);

        if($sellerData){
            $sellerData = $sellerData->toArray();
        }
        //vendo se está ativo e o token está válido.
        $timestamp = $sellerData['token_valid_at'];
        $target = Carbon::createFromTimestamp($timestamp);
        $now    = now(); // Carbon::now() em UTC se APP_TIMEZONE=UTC

        $minutesLeft = $now->diffInMinutes($target, false);

        if($minutesLeft <= 0){
            Log::channel('process')->info('TOKEN VENCIDO');
            //o token está vencido, então aborta o processo
            updateCallback($idCallBack, 4, ['Token do seller esta vencido!']);
            return false;
        }

        if($minutesLeft <= 3){
            Log::channel('process')->info('TOKEN VENCENDO!');
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
            Log::channel('process')->info('ITEM SEM PREÇO: ' . $itemId);
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
            Log::channel('process')->info('RESOURCE SEM PREÇO: ', $resource);
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
                    updateCallback($idCallBack, 3, ['Attempt' => $attempt, 'message' => 'Produto não existe no sistema!']);
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

            $this->updateProduct($item, $token, $itemId, $userId);

        }else{
            //Não temos os dados desse produto, então vamos cadastrar;

            //Evitando loop infinito
            if($attempt > 0){
                //já tentou uma vez então aborta o processo e registra o erro
                updateCallback($idCallBack, 3, ['Attempt' => $attempt, 'message' => 'Nao conseguimos salvar a publicacao!']);
                return false;
            }

            $this->updateProduct($item, $token, $itemId, $userId);

            //reprograma o processo
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataRequest['dataCallback'], 'attempt' => 1], 300, 'default');
            updateCallback($idCallBack, 2, ['message' => 'Não tinhamos o anuncio, estamos criando o cadastro e reprogramando verificação.']);
            return false;

        }

        // Log::channel('process')->info('1- Item:' . $itemId . ' Venda:' . $salePrice . ' Minimo: ' . $minimalPriceToUse);
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

            updateCallback($idCallBack, 2, [$updatePrice]);

            Log::channel('process')->info('3- setItemPrice:' . $updateResult . ' ItemId: ' . $itemId);

            if($updateResult > 0){
                $pauseAdd = false;
                $dataInfraction['punish'] = 'Iniciada atualização de preço.';

                //Rodar o Job de confirmação.
                $dataRecheck = [
                    'userId'            =>  $userId,
                    'idCallBack'        =>  $idCallBack,
                    'minimalPriceToUse' =>  $minimalPriceToUse,
                    'sellerId'          =>  $sellerId,
                    'itemId'            =>  $itemId,
                    'token'             =>  $token,
                    'resource'          =>  $resource
                ];
                dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'checkPricesInbound', $dataRecheck, 60, 'default');
            }
            
            //2 - Pausar anúncio se não conseguiu atualizar os preços.
            if($pauseAdd){
                $paused = $this->meli_communications->pauseItem($itemId, $token);
                $resultPause = data_get($paused, 'data.status', false);
                Log::channel('process')->info('11- PAUSAR:' . $resultPause . ' ItemId: ' . $itemId);
                if($resultPause == 'paused'){
                    $dataInfraction['punish'] = 'O anúncio foi pausado';
                }
            }

            //3 - Registrar a infração.
            $infraction = $this->price_infractions_connections->insertGetId($dataInfraction);

            //4 - Enviar mensagem ao usuário.
            $punish = $dataInfraction['punish'];
            if($punish){
                $title = 'Infração';
                $messageText = "O anúncio { $itemId } do Mercado Livre estava abaixo do preço mínimo. Resultado: { $punish }";
            }else{
                $title = 'Infração';
                $messageText = "O anúncio { $itemId } do Mercado Livre está abaixo do preço mínimo. Atualize o preço para no mínimo R$ " . brMoney($minimalPriceToUse);
            }
            
            $messageId = "Price_Infraction_" . $itemId . date('y_m_d');

            createSystemMessage($messageId, $userId, $title, $messageText, 1);

        }else{
            updateCallback($idCallBack, 2, ['Produto dentro do preço mínimo' => ['minimo' => $minimalPriceToUse, 'anuncio' => $salePrice]]);
        }

        Log::channel('process')->info('SAINDO - pricesInbound , ItemId: ' . $itemId);
    }

    public function checkPricesInbound($dataRecheck){
        $token = $dataRecheck['token'];
        $userId = $dataRecheck['userId'];
        $minimalPriceToUse = $dataRecheck['minimalPriceToUse'];
        $itemId = $dataRecheck['itemId'];
        $resource = $dataRecheck['resource'];

        //Consultar o valor de venda.
        $priceData = $this->meli_communications->getItemPrice($token, $resource);
        
        $salePrice = 9999999999;
        //passou, então vamos verificar o menor preço praticado pelo anúncio:
        foreach($priceData['data']['prices'] as $value) {
            if($value['amount'] < $salePrice){
                $salePrice = $value['amount'];
            }
        }

        if($salePrice < $minimalPriceToUse){
            //Continua abaixo, pausa o anúncio.
            $paused = $this->meli_communications->pauseItem($itemId, $token);
            $resultPause = data_get($paused, 'data.status', false);
            if($resultPause == 'paused'){
                $title = 'Infração';
                $messageId = "Price_Infraction_" . $itemId . date('y_m_d') . 'rechek';
                $messageText = "O anúncio { $itemId } do Mercado Livre estava abaixo do preço mínimo e foi pausado!";
                createSystemMessage($messageId, $userId, $title, $messageText, 1);
            }

        }
        
    }


    // SHOPEE
    public function pricesInboundShopee($dataRequest){
        // Verificar preço, se baix: alerar, Se não altera: pausar
        $idCallBack = $dataRequest['idCallBack'];
        $sellerId = $dataRequest['dataCallback']['company_id'];
        $resource = $dataRequest['dataCallback']['reference_id'];
        $attempt = $dataRequest['attempt'];

        if($idCallBack > 0){
            //processo iniciado:
            updateCallback($idCallBack, 1, ['Iniciado processo']);
        }
        //consultar dados do seller:
        $sellerData = $this->authentication_connections->findBy('user_channel_id', $sellerId);

        if(isset($sellerData->user_id)){
            $sellerData = $sellerData->toArray();
        }

        //vendo se está ativo e o token está válido.
        $timestamp = $sellerData['token_valid_at'] ;
        $target = Carbon::createFromTimestamp($timestamp);
        $now    = now(); // Carbon::now() em UTC se APP_TIMEZONE=UTC

        $minutesLeft = $now->diffInMinutes($target, false);

        if($minutesLeft <= 0){
            Log::channel('process')->info('TOKEN VENCIDO');
            //o token está vencido, então aborta o processo
            updateCallback($idCallBack, 4, ['Token do seller esta vencido!']);
            return false;
        }

        if($minutesLeft <= 3){
            Log::channel('process')->info('TOKEN VENCENDO!');
            //o token está prestes a vencer, então reprograma o processo.
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataRequest['dataCallback'], 'attempt' => 0], 300, 'default');
            updateCallback($idCallBack, 3, ['Token do seller esta prestes a vencer, reprogramado processo!']);
        }

        //consultando o usuário do sistema
        $userData = $this->user_connections->getById($sellerData['user_id']);
        $userPriceGroupId = $userData['price_group']??null;

        $userId = $userData['id'];
        $token = $sellerData['token'];

        //verificar se é com ou sem model:
        $explodeResource = explode(':', $resource);
        $itemId = (int)$explodeResource[0];
        $modelId = $explodeResource[1] ?? null;

        //Consultando o item na Shopee
        $itemData = $this->shopee_communications->getItemBaseInfo($sellerId, (int)$itemId, $token);

        if(!$itemData['success']){
            Log::channel('process')->info('ITEM SEM PREÇO: ' . $itemId);
            //Não conseguimos consultar os preços, então aborta o processo
            updateCallback($idCallBack, 3, ['Erro ao consultar o anuncio' => $itemData]);
            return false;
        }

        $item = data_get($itemData, 'data.response.item_list.0');

        //consultando o model se houver
        if($modelId){
            $modelData = $this->shopee_communications->getModelList($sellerId, (int)$itemId, $token);
            if($modelData['success']){
                foreach($modelData['data']['response']['model'] as $key => $model){
                    if($model['model_id'] == $modelId){
                        $item['model'] = $model;
                        break;
                    }
                }
                
            }
        }

        $itemName = $item['item_name'] . ($item['model'] ? ' - ' . $item['model']['model_name'] : '');
        $permalink = "https://shopee.com.br/product/" . $sellerId . "/" . $itemId . "/";

        //buscando o preço do anúncio.
        if($item['model']){
            $salePrice = $item['model']['price_info'][0]['current_price'] ?? 9999999999;
            $modelId = $item['model']['model_id'];
            // $itemSku = $item['model']['model_sku'] ?? null;
            // $itemEan = $item['model']['gtin_code'] ?? null;
        }else{
            $salePrice = $item['price_info'][0]['current_price'] ?? 9999999999;
            $modelId = 0;
            // $itemSku = $item['item_sku'] ?? null;
            // $itemEan = $item['gtin_code'] ?? null;
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
                    updateCallback($idCallBack, 3, ['Attempt' => $attempt, 'message' => 'Produto não existe no sistema!']);
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
                            $minimalPriceToUse = data_get($pGroup, 'values.shopee.minimum_selling', 0);
                            if($minimalPriceToUse == 0){    
                                $minimalPriceToUse = data_get($pGroup, 'values.outros.minimum_selling', 0);
                            }
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


            //reprograma o processo
            dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataRequest['dataCallback'], 'attempt' => 1], 300, 'default');
            updateCallback($idCallBack, 2, ['message' => 'Não tinhamos o anuncio, estamos criando o cadastro e reprogramando verificação.']);
            return false;

        }

        // Log::channel('process')->info('1- Item:' . $itemId . ' Venda:' . $salePrice . ' Minimo: ' . $minimalPriceToUse);
        // Agora verificamos se o anúncio está abaixo do preço mínimo    $sellerId

        if ($salePrice < $minimalPriceToUse){
            //Está abaixo do mínimo.
            $dataInfraction = [
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'sku' => $sku,
                'channel' => 'SHOPEE',
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

            $updatePrice = $this->shopee_communications->updateModelPrice($sellerId, $itemId, $modelId, $minimalPriceToUse, $token);

            $updateResult = data_get($updatePrice, 'data.response.success_list.0.original_price', 0);

            updateCallback($idCallBack, 2, [$updatePrice]);

            if($updateResult > 0){
                $pauseAdd = false;
                $dataInfraction['punish'] = 'Iniciada atualização de preço.';
            }
            
            //2 - Pausar anúncio se não conseguiu atualizar os preços.
            if($pauseAdd){
                $paused = $this->shopee_communications->updateStatusITem($sellerId, $itemId, 'UNLIST', $token);

                $resultPause = data_get($paused, 'success', false);

                if($resultPause == 'paused'){
                    $dataInfraction['punish'] = 'O anúncio foi pausado';
                }
            }

            //3 - Registrar a infração.
            $infraction = $this->price_infractions_connections->insertGetId($dataInfraction);

            //4 - Enviar mensagem ao usuário.
            $punish = $dataInfraction['punish'];
            if($punish){
                $title = 'Infração';
                $messageText = "O anúncio { $itemId } da Shopee estava abaixo do preço mínimo. Resultado: { $punish }";
            }else{
                $title = 'Infração';
                $messageText = "O anúncio { $itemId } da Shopee está abaixo do preço mínimo. Atualize o preço para no mínimo R$ " . brMoney($minimalPriceToUse);
            }
            
            $messageId = "Price_Infraction_" . $itemId . date('y_m_d');

            createSystemMessage($messageId, $userId, $title, $messageText, 1);

        }else{
            updateCallback($idCallBack, 2, ['Produto dentro do preço mínimo' => ['minimo' => $minimalPriceToUse, 'anuncio' => $salePrice]]);
        }

        Log::channel('process')->info('SAINDO - pricesInbound , ItemId: ' . $itemId);
    
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

    private function updateProduct($item, $token, $itemId, $userId){
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