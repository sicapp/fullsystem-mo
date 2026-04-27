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

        dispatchGenericJob(\App\Services\Functions\MonitoringFunction::class, 'getItemToMonitoring', [], 0, 'default');
        dd('Concluído');
        

        // dispatchGenericJob(\App\Services\Functions\MonitoringFunction::class, 'getItemToMonitoring', [], 0, 'default');
        // dispatchGenericJob(\App\Services\Functions\SearchAdsFunctions::class, 'findAds', [], 0, 'default'); // Atualiza anúncios 1 vez ao dia.
    }
    
}