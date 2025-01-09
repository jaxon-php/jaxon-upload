<?php

namespace Jaxon\Upload;

use Jaxon\Di\Container;
use Jaxon\App\Config\ConfigEventManager;
use Jaxon\App\Config\ConfigListenerInterface;
use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Request\Upload\UploadHandlerInterface;
use Jaxon\Upload\Manager\FileNameInterface;
use Jaxon\Upload\Manager\FileStorage;
use Jaxon\Upload\Manager\UploadManager;
use Jaxon\Upload\Manager\Validator;
use Jaxon\Utils\Config\Config;

use function Jaxon\jaxon;
use function bin2hex;
use function php_sapi_name;
use function random_bytes;
use function realpath;

/**
 * @param Container $di
 * @param bool $bForce Force registration
 *
 * @return void
 */
function setupDi(Container $di, bool $bForce = false)
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
        $xFileStorage = new FileStorage($c->g(ConfigManager::class), $c->g(Translator::class));
        $xFileStorage->registerAdapters();
        return $xFileStorage;
    });
    // File upload manager
    $di->set(UploadManager::class, function($c) {
        // Translation directory
        $sTranslationDir = realpath(__DIR__ . '/../../translations');
        // Load the upload translations
        $xTranslator = $c->g(Translator::class);
        $xTranslator->loadTranslations($sTranslationDir . '/en/upload.php', 'en');
        $xTranslator->loadTranslations($sTranslationDir . '/fr/upload.php', 'fr');
        $xTranslator->loadTranslations($sTranslationDir . '/es/upload.php', 'es');

        return new UploadManager($c->g(FileStorage::class), $c->g(FileNameInterface::class),
            $c->g(ConfigManager::class), $c->g(Validator::class), $xTranslator);
    });
    // File upload plugin
    $di->set(UploadHandler::class, function($c) {
        return new UploadHandler($c->g(FileStorage::class), $c->g(UploadManager::class));
    });
    // Set alias on the interface
    $di->alias(UploadHandlerInterface::class, UploadHandler::class);
}

/**
 * Register the values into the container
 *
 * @return void
 */
function _register()
{
    $jaxon = jaxon();
    $di = $jaxon->di();
    $sEventListenerKey = UploadHandler::class . '\\ConfigListener';
    if($di->h($sEventListenerKey))
    {
        return;
    }

    // The upload package is installed, the upload manager must be registered,
    // but only when the feature is activated in the config.
    $di->set($sEventListenerKey, function() {
        return new class implements ConfigListenerInterface
        {
            public function onChange(Config $xConfig, string $sName)
            {
                $sConfigKey = 'core.upload.enabled';
                if(($sName === $sConfigKey || $sName === '') && $xConfig->getOption($sConfigKey))
                {
                    setupDi(jaxon()->di());
                }
            }
        };
    });

    // Listener for app config changes.
    $jaxon->config()->addLibEventListener($sEventListenerKey);
}

function register()
{
    // Do nothing if running in cli.
    if(php_sapi_name() !== 'cli')
    {
        _register();
    };
}

register();
