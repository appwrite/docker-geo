<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Utopia\Http\Response;
use Utopia\Http\Validator\Text;
use Utopia\Platform\Action;

class Get extends Action
{
    public static function getName(): string
    {
        return 'getLocale';
    }

    public function __construct()
    {
        $this
            ->setHttpMethod(Action::HTTP_REQUEST_METHOD_GET)
            ->setHttpPath('/v1/ips/:ip')
            ->desc('Get locale from IP')
            ->param('ip', '', new Text(100), 'IP Address')
            ->groups(['api'])
            ->inject('response')
            ->callback(fn ($ip, $response) => $this->action($ip, $response));
    }

    public function action(string $ip, Response $response)
    {
        $response->end('Hello IP: ' . $ip);
    }
}
