<?php

namespace App\Http\Controllers;

use App\Services\Communications\MARKETPLACE\SHOPEE\ShopeeCommunications;
use App\Services\Functions\MonitoringFunction;
use App\Services\Functions\SearchAdsFunctions;
use Illuminate\Contracts\Queue\Monitor;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function __construct(
        protected SearchAdsFunctions $search_ads_functions,
        protected MonitoringFunction $monitoring_function,
        protected ShopeeCommunications  $shopee_communications,

    ) {}

    public function devTeste(Request $request)
    {
        $shopId = 226662089;
        $itemId = 885177652;
        $result = $this->shopee_communications->getModelList($shopId, $itemId);
        dd($result);
        
        $this->monitoring_function->getItemToMonitoring();

        return 'Iniciado getItemToMonitoring';

        $result = $this->search_ads_functions->findAds();
    
        $this->monitoring_function->getItemToMonitoring();
    }
    
}