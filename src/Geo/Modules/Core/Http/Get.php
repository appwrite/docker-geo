<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Utopia\Http\Response;
use Utopia\Http\Validator\Text;
use Utopia\Platform\Action;
use MaxMind\Db\Reader;

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
            ->inject('geodb')
            ->inject('response')
            ->callback(fn ($ip, $geodb, $response) => $this->action($ip, $geodb, $response));
    }


    public function action(string $ip, Reader $geodb /** @phpstan-ignore class.notFound */, Response $response): void
    {
        $output['ip'] = $ip;

        // @phpstan-ignore-next-line
        $record = $geodb->get($ip);

        if ($record) {
            $output['countryCode'] = $record['country']['iso_code'];
            $output['country'] = $record['country']['names'];
            $output['continent'] = $record['continent']['names'];
            $output['continentCode'] = $record['continent']['code'];
        } else {
            $output['countryCode'] = '--';
            $output['country'] = '';
            $output['continent'] = '';
            $output['continentCode'] = '--';
        }
        $response->json($output);
        $response->end();
    }
}
