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
            ->groups(['api'])
            ->inject('request')
            ->callback(fn ($request)
                => $this->action($request));
    }

    public function action(Request $request): void
    {
        $authHeader = $request->getHeaderLine('authorization', '');
        $parts = \explode(' ', $authHeader, 2);

        if (\count($parts) !== 2 || \strtolower($parts[0]) !== 'bearer') {
            throw new Exception('Missing or invalid authorization header', 401);
        }

        $secretKey = $parts[1];
        if (empty($secretKey) || $secretKey !== System::getEnv('GEO_SECRET', '')) {
            throw new Exception('Invalid Geo server key', 401);
        }
    }
}
