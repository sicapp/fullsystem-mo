<?php

namespace App\Http\Controllers;

use App\Services\Functions\SearchAdsFunctions;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function __construct(
        protected SearchAdsFunctions $search_ads_functions
    ) {}

    public function devTeste(Request $request)
    {
        $result = $this->search_ads_functions->findAds();     
    }
    
}