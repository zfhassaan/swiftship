<?php

namespace zfhassaan\swiftship\Facade;

use Illuminate\Support\Facades\Facade;

class SwiftShipFacade extends Facade
{
    /**
     * Get the registered name of the component
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'swiftship';
    }
}
