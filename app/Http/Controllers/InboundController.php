<?php

namespace App\Http\Controllers;

use App\Services\DbConnections\CallbackConnections;
use Illuminate\Http\Request;

class InboundController extends Controller
{

    public function __construct(
        protected CallbackConnections $callbackConnections,
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

        }elseif($request->input('origin') == 'BLING'){
            $dataCallback = [
                'origin' => $request->input('origin'),
                'reference' => $request->input('event'),
                'reference_id' => $request->input('data.id'),
                'company_id' => $request->input('companyId'),
                'status' => 0,
                'data' => json_encode($request->all()),
                'data_status' => null,
                'created_at' => dateUtc(),
                'updated_at' => dateUtc()
            ];

        }elseif($request->input('origin') == 'MANUAL'){
            $dataCallback = [
                'origin' => $request->input('origin'),
                'reference' => $request->input('code') . ':' . $request->input('topic'),
                'reference_id' => $request->input('resource'),
                'company_id' => $request->input('companyId'),
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
                dispatchGenericJob(\App\Services\Inbounds\DistributorInbound::class, 'inputDistributor', ['idCallBack' => $idCallBack, 'dataCallback' => $dataCallback], 0, 'beInbound');
            }
        }
    
        return response('ok', 200);

    }
}
