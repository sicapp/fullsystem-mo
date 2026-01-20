<?php


namespace App\Services\DbConnections;

use App\Models\PriceInfractions;

class PriceInfractionsConnections extends BaseConnection
{

    protected string $modelClass = PriceInfractions::class;
	
}