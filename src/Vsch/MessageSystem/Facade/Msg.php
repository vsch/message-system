<?php 

namespace Vsch\MessageSystem\Facade;

use Illuminate\Support\Facades\Facade;

class Msg extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'msg'; }

}
