<?php

namespace Appwrite\Geo\Modules;
use Appwrite\Geo\Modules\Core\Services\Http;
use Utopia\Platform\Module;

class Core extends Module
{
    public function __construct()
    {
        $this->addService('http', new Http());
    }
}