<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Utopia\Http\Response;
use Utopia\Platform\Action;

class Health extends Action
{
    public static function getName(): string
    {
        return 'health';
    }

    public function __construct()
    {
        $this
            ->setHttpMethod(Action::HTTP_REQUEST_METHOD_GET)
            ->setHttpPath('/v1/health')
            ->desc('Health check')
            ->groups(['health'])
            ->inject('response')
            ->callback(fn ($response) => $this->action($response));
    }

    public function action(Response $response): void
    {
        $response->json(['status' => 'ok']);
    }
}
