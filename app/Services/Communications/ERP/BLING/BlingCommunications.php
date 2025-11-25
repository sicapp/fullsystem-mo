<?php

namespace App\Services\Communications\ERP\BLING;

use Illuminate\Support\Facades\Http;

class BlingCommunications
{
    // ========================================================
    // === MÓDULO: Produtos ===================================
    // ========================================================

    // Obter dados básicos da empresa
    public function getUserData($token): ?array
    {
        $url = config('services.bling.url') . 'empresas/me/dados-basicos';

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($url);

        return self::returnResponse($response);
    }

    public function getProductBySku($token, $sku){

        $url = config('services.bling.url') . "/produtos?codigos[]=$sku";

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($url);

        return self::returnResponse($response);
    }


    /*
     * Função que trata as respostas e erros da API
     */
    private function returnResponse($response, $headers = null){

        if ($response->successful()) {
            $return = $response->json();
            $return['headers'] = $headers;
            $return['http'] = $response->status();
            return $return;
        }

        return [
            'error' => true,
            'status' => $response->status(),
            'message' => $response->json()
        ];
    }

}
