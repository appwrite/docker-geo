<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Throwable;
use Utopia\Http\Http;
use Utopia\Http\Response;
use Utopia\Http\Route;
use Utopia\Platform\Action;
use Utopia\Span\Span;
use Utopia\System\System;

class Error extends Action
{
    public static function getName(): string
    {
        return 'error';
    }

    public function __construct()
    {
        $this->setType(Action::TYPE_ERROR);

        $this
            ->groups(['*'])
            ->inject('route')
            ->inject('error')
            ->inject('response')
            ->callback(fn ($route, $error, $response) => $this->action($route, $error, $response));
    }

    public function action(?Route $route, Throwable $error, Response $response): void
    {
        $span = Span::current() ?? Span::init('http.request');
        $span->setError($error);

        // Client errors stay on the span but are not exported
        $span->set('error.publish', $error->getCode() >= 500 || $error->getCode() === 0);
        $span->set('error.type', \get_class($error));
        $span->set('error.code', $error->getCode());

        if ($route) {
            $span->set('http.method', $route->getMethod());
            $span->set('http.path', $route->getPath());
        }

        $version = System::getEnv('GEO_VERSION', 'UNKNOWN');
        $message = $error->getMessage();
        $file = $error->getFile();
        $line = $error->getLine();
        $trace = $error->getTrace();

        switch ($error->getCode()) {
            case 400: // Error allowed publicly
            case 401: // Error allowed publicly
            case 402: // Error allowed publicly
            case 403: // Error allowed publicly
            case 404: // Error allowed publicly
            case 406: // Error allowed publicly
            case 409: // Error allowed publicly
            case 412: // Error allowed publicly
            case 425: // Error allowed publicly
            case 429: // Error allowed publicly
            case 501: // Error allowed publicly
            case 503: // Error allowed publicly
                $code = $error->getCode();
                break;
            default:
                $code = 500; // All other errors get the generic 500 server error status code
        }

        $output = Http::isDevelopment() ? [
            'message' => $message,
            'code' => $code,
            'file' => $file,
            'line' => $line,
            'trace' => \json_encode($trace, JSON_UNESCAPED_UNICODE) === false ? [] : $trace,
            'version' => $version
        ] : [
            'message' => $message,
            'code' => $code,
            'version' => $version
        ];

        $response
            ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->addHeader('Expires', '0')
            ->addHeader('Pragma', 'no-cache')
            ->setStatusCode($code);

        $response->json($output);

        $span->finish();
    }
}
