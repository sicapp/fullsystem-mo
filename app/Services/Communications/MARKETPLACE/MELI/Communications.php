<?php

namespace App\Services\Communications\MARKETPLACE\Meli;
use Illuminate\Support\Facades\Http;

abstract class Communications
{
	 public function get($uri, $token = null, $params = null, $decode=true, $header = null){
        if($token != null && $header == null){
            $dataReturn = Http::withHeaders([ 'Authorization' => 'Bearer ' . $token,])
                ->get($uri, $params);
        }elseif($token == null && $header != null){
            $dataReturn = Http::withHeaders($header)
                ->get($uri, $params);
        }elseif($token != null && $header != null){
            $headers = array_merge(
                ['Authorization' => 'Bearer ' . $token],
                $header
            );

            $dataReturn = Http::withHeaders($headers)->get($uri, $params);
        }else{
            $dataReturn = Http::get($uri, $params);
        }

        if($dataReturn->status() == 200) {
            if($decode){
                return ['data' => json_decode($dataReturn->body(), true), 'success' => true, 'status'=>$dataReturn->status()];
            }else{
                return ['data' => $dataReturn->body(), 'success' => true, 'status'=>$dataReturn->status()];
            }

        }else{
            return ['data' => null, 'success'=>false, 'return'=>$dataReturn->body(), 'status'=>$dataReturn->status()];
        }
    }

    public function post($uri, $token = null, $params = null, $decode=true, $header = null){

        if($token != null){
            $dataReturn = Http::withHeaders([ 'Authorization' => 'Bearer ' . $token,])
                ->post($uri, $params);
        }else{
            $dataReturn = Http::post($uri, $params);
        }

        if($dataReturn->status() == 200){
            if($decode){
                return ['data' => json_decode($dataReturn->body(), true), 'success' => true, 'status'=>$dataReturn->status()];
            }else{
                return ['data' => $dataReturn->body(), 'success' => true, 'status'=>$dataReturn->status()];
            }
        }else{
            return ['data' => null, 'success'=>false, 'return'=>$dataReturn->body(), 'status'=>$dataReturn->status()];
        }
    }

    public function put($uri, $token = null, $params = null, $decode=true, $header = null){
        if($token != null){
            $dataReturn = Http::withHeaders([ 'Authorization' => 'Bearer ' . $token,])
                ->put($uri, $params);
        }else{
            $dataReturn = Http::put($uri, $params);
        }

        if($dataReturn->status() == 200){
            if($decode){
                return ['data' => json_decode($dataReturn->body(), true), 'success' => true, 'status'=>$dataReturn->status()];
            }else{
                return ['data' => $dataReturn->body(), 'success' => true, 'status'=>$dataReturn->status()];
            }
        }else{
            return ['data' => null, 'success'=>false, 'return'=>$dataReturn->body(), 'status'=>$dataReturn->status()];
        }
    }

    public function delete($uri, $token = null, $params = null, $decode=true, $header = null){
        if($token != null){
            $dataReturn = Http::withHeaders([ 'Authorization' => 'Bearer ' . $token,])
                ->delete($uri, $params);
        }else{
            $dataReturn = Http::delete($uri, $params);
        }

        if($dataReturn->status() == 200){
            if($decode){
                return ['data' => json_decode($dataReturn->body(), true), 'success' => true, 'status'=>$dataReturn->status()];
            }else{
                return ['data' => $dataReturn->body(), 'success' => true, 'status'=>$dataReturn->status()];
            }
        }else{
            return ['data' => null, 'success'=>false, 'return'=>$dataReturn->body(), 'status'=>$dataReturn->status()];
        }
    }
}