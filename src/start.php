<?php

namespace Jaxon\Upload;

use Jaxon\App\Config\ConfigEventManager;
use Jaxon\App\Config\ConfigListenerInterface;
use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Di\Container;
use Jaxon\Request\Upload\UploadHandlerInterface;
use Jaxon\Response\Manager\ResponseManager;
use Jaxon\Upload\Manager\FileNameInterface;
use Jaxon\Upload\Manager\FileStorage;
use Jaxon\Upload\Manager\UploadManager;
use Jaxon\Upload\Manager\Validator;
use Jaxon\Utils\Config\Config;
use Nyholm\Psr7\Factory\Psr17Factory;

use function bin2hex;
use function random_bytes;

/**
 * @param Container $di
 * @param bool $bForce Force registration
 *
 * @return void
 */
function register(Container $di, bool $bForce = false)
{
    if(!$bForce && $di->h(UploadHandler::class))
    {
        return;
    }

    // Upload file and dir name generator
    $di->set(FileNameInterface::class, function() {
        return new class implements FileNameInterface
        {
            public function random(int $nLength): string
            {
                return bin2hex(random_bytes((int)($nLength / 2)));
            }
        };
    });
    // Upload validator
    $di->set(Validator::class, function($c) {
        return new Validator($c->g(ConfigManager::class), $c->g(Translator::class));
    });
    // File storage
    $di->set(FileStorage::class, function($c) {
        return new FileStorage($c->g(ConfigManager::class), $c->g(Translator::class));
    });
    // File upload manager
    $di->set(UploadManager::class, function($c) {
        return new UploadManager($c->g(FileNameInterface::class), $c->g(ConfigManager::class),
            $c->g(Validator::class), $c->g(Translator::class), $c->g(FileStorage::class));
    });
    // File upload plugin
    $di->set(UploadHandler::class, function($c) {
        return new UploadHandler($c->g(UploadManager::class), $c->g(ResponseManager::class),
            $c->g(Translator::class), $c->g(Psr17Factory::class));
    });
    // Set alias on the interface
    $di->alias(UploadHandlerInterface::class, UploadHandler::class);
}

/**
 * Register the values into the container
 *
 * @return void
 */
function registerUpload()
{
    $di = jaxon()->di();
    $sEventListenerKey = UploadHandler::class . '\\ConfigListener';
    if($di->h($sEventListenerKey))
    {
        return;
    }

    // The annotation package is installed, register the real annotation reader,
    // but only if the feature is activated in the config.
    $di->set($sEventListenerKey, function() {
        return new class implements ConfigListenerInterface
        {
            public function onChange(Config $xConfig, string $sName)
            {
                $sConfigKey = 'core.upload.enabled';
                if(($sName === $sConfigKey || $sName === '') && $xConfig->getOption($sConfigKey))
                {
                    register(jaxon()->di());
                }
            }
        };
    });

    // Register the event listener
    $xEventManager = $di->g(ConfigEventManager::class);
    $xEventManager->addListener($sEventListenerKey);
}

// Initialize the upload handler
registerUpload();
