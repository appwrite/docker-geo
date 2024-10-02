<?php

namespace Appwrite\Geo\Modules\Core\Http;

use Throwable;
use Utopia\CLI\Console;
use Utopia\Http\Http;
use Utopia\Http\Response;
use Utopia\Http\Route;
use Utopia\Logger\Log;
use Utopia\Logger\Logger;
use Utopia\Platform\Action;
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
            ->inject('logger')
            ->inject('response')
            ->inject('log')
            ->callback(fn ($route, $error, $logger, $response, $log) => $this->action($route, $error, $logger, $response, $log));
    }

    public function action(?Route $route, Throwable $error, ?Logger $logger, Response $response, Log $log): void
    {
        $this->logError($log, $error, "httpError", $logger, $route);

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
            'trace' => \json_encode($trace, JSON_UNESCAPED_UNICODE) === false ? [] : $trace, // check for failing encode
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
    }

    protected function logError(Log $log, Throwable $error, string $action, Logger $logger = null, Route $route = null): void
    {
        Console::error('[Error] Type: ' . get_class($error));
        Console::error('[Error] Message: ' . $error->getMessage());
        Console::error('[Error] File: ' . $error->getFile());
        Console::error('[Error] Line: ' . $error->getLine());

        if ($logger && ($error->getCode() === 500 || $error->getCode() === 0)) {
            $version = (string) System::getEnv('GEO_VERSION', '');
            if (empty($version)) {
                $version = 'UNKNOWN';
            }

            $log->setNamespace("executor");
            $log->setServer(\gethostname() !== false ? \gethostname() : null);
            $log->setVersion($version);
            $log->setType(Log::TYPE_ERROR);
            $log->setMessage($error->getMessage());

            if ($route) {
                $log->addTag('method', $route->getMethod());
                $log->addTag('url', $route->getPath());
            }

            $log->addTag('code', \strval($error->getCode()));
            $log->addTag('verboseType', get_class($error));

            $log->addExtra('file', $error->getFile());
            $log->addExtra('line', $error->getLine());
            $log->addExtra('trace', $error->getTraceAsString());
            // TODO: @Meldiron Uncomment, was warning: Undefined array key "file" in Sentry.php on line 68
            // $log->addExtra('detailedTrace', $error->getTrace());

            $log->setAction($action);

            $log->setEnvironment(Http::isProduction() ? Log::ENVIRONMENT_PRODUCTION : Log::ENVIRONMENT_STAGING);
            try {
                $responseCode = $logger->addLog($log);
                Console::info('Geo log pushed with status code: ' . $responseCode);
            } catch (Throwable $th) {
                Console::error('Error pushing log: ' . $th->getmessage());
                Console::error($th->getTraceAsString());
            }
        }
    }
}
