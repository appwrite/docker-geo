<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Exception;
use Utopia\Http\Request;
use Utopia\Platform\Action;
use Utopia\System\System;

class Init extends Action
{
    public static function getName(): string
    {
        return 'init';
    }

    public function __construct()
    {
        $this->setType(Action::TYPE_INIT);

        $this
            ->groups(['*'])
            ->inject('request')
            ->callback(fn ($request)
                => $this->action($request));
    }

    public function action(Request $request)
    {
        $secretKey = \explode(' ', $request->getHeader('authorization', ''))[1] ?? '';
        if (empty($secretKey) || $secretKey !== System::getEnv('GEO_SECRET', '')) {
            throw new Exception('Missing Geo server key', 401);
        }
    }
}
