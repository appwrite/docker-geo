<?php

namespace Appwrite\Geo\Platform;

use Appwrite\Geo\Modules\Core;
use Utopia\Platform\Platform;

class Geo extends Platform
{
    public function __construct()
    {
        $this->addModule(new Core());
    }
}
