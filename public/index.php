<?php

use Doctrine\DBAL\Exception\ConnectionException;
use PackageVersions\Versions;
use Shopware\Development\Kernel;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$classLoader = require __DIR__.'/../vendor/autoload.php';

// The check is to ensure we don't use .env if APP_ENV is defined
if (!isset($_SERVER['APP_ENV']) && !isset($_ENV['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
    }
    (new Dotenv(true))->load(__DIR__.'/../.env');
}

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ('prod' !== $env));

if ($debug) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

// resolve SEO urls
$request = Request::createFromGlobals();
$connection = Kernel::getConnection();

if ($env === 'dev') {
    $connection->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\DebugStack());
}

try {
    $request = (new RequestTransformer($connection))
        ->transform($request);

    $shopwareVersion = Versions::getVersion('shopware/platform');

    $kernel = new Kernel($env, $debug, $classLoader, $shopwareVersion, $connection);

    $response = $kernel->handle($request);
} catch (ConnectionException $e) {
    throw new RuntimeException($e->getMessage());
}
$response->send();
$kernel->terminate($request, $response);
