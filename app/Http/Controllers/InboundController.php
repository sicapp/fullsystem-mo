<?php

namespace App\Http\Controllers;

use App\Services\DbConnections\CallbackConnections;
use App\Services\Functions\SearchAdsFunctions;
use Illuminate\Http\Request;

class InboundController extends Controller
{

    public function __construct(
        protected CallbackConnections $callbackConnections,
        protected SearchAdsFunctions $search_ads_functions,
    ) {}

    public function inboundSe(Request $request){

        $dataCallback = null;

        if($request->input('origin') == 'MELI'){
            $dataCallback = [
                'origin' => $request->input('origin'),
                'reference' => $request->input('topic'),
                'reference_id' => $request->input('resource'),
                'company_id' => $request->input('user_id'),
                'status' => 0,
                'data' => json_encode($request->all()),
                'data_status' => null,
                'created_at' => dateUtc(),
                'updated_at' => dateUtc()
            ];

        }

        if($dataCallback){
            $idCallBack = $this->callbackConnections->insertGetId($dataCallback, __FUNCTION__);
            if($idCallBack){
                dispatchGenericJob(\App\Services\Functions\InboundPricesFunctions::class, 'pricesInbound', ['idCallBack' => $idCallBack, 'dataCallback' => $dataCallback, 'attempt' => 0], 0, 'default');
            }
        }
    
        return response('ok', 200);

    }

    public function inboundBe(Request $request){
        $authId = $request->input('authId');
        if($request->route('task') == 'findAds' && $authId){
            $this->search_ads_functions->findAds($authId);
        }
    }
}
