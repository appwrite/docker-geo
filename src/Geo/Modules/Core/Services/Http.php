<?php

namespace Appwrite\Geo\Modules\Core\Services;

use Appwrite\Geo\Modules\Core\Http\Get;
use Appwrite\Geo\Modules\Core\Http\Init;
use Appwrite\Geo\Modules\Core\Http\Error;
use Utopia\Platform\Service;

class Http extends Service
{
    public function __construct()
    {
        $this->type = Service::TYPE_HTTP;
        $this->addAction(Get::getName(), new Get());
        $this->addAction(Init::getName(), new Init());
        $this->addAction(Error::getName(), new Error());
    }
}
