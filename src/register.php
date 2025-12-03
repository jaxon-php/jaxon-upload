<?php

namespace Jaxon\Upload;

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Request\Upload\UploadHandlerInterface;
use Jaxon\Storage\StorageManager;
use Jaxon\Upload\Manager\FileNameInterface;
use Jaxon\Upload\Manager\UploadManager;
use Jaxon\Upload\Manager\Validator;
use Psr\Log\LoggerInterface;

use function Jaxon\jaxon;
use function bin2hex;
use function dirname;
use function php_sapi_name;
use function random_bytes;

/**
 * @return void
 */
function registerUpload(): void
{
    $jaxon = jaxon();
    if(!$jaxon->getOption('upload.enabled') && !$jaxon->getOption('core.upload.enabled'))
    {
        return;
    }

    $di = $jaxon->di();
    if($di->h(UploadHandler::class))
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
    $di->set(Validator::class, fn($c) =>
        new Validator($c->g(ConfigManager::class), $c->g(Translator::class)));

    // File upload manager
    $di->set(UploadManager::class, function($c) {
        // Translation directory
        $sTranslationDir = dirname(__DIR__) . '/translations';
        // Load the upload translations
        $xTranslator = $c->g(Translator::class);
        $xTranslator->loadTranslations("$sTranslationDir/en/upload.php", 'en');
        $xTranslator->loadTranslations("$sTranslationDir/fr/upload.php", 'fr');
        $xTranslator->loadTranslations("$sTranslationDir/es/upload.php", 'es');

        return new UploadManager($c->g(Validator::class), $xTranslator,
            $c->g(LoggerInterface::class), $c->g(FileNameInterface::class),
            $c->g(StorageManager::class), $c->g(ConfigManager::class));
    });

    // File upload plugin
    $di->set(UploadHandler::class, fn($c) =>
        new UploadHandler($c->g(StorageManager::class), $c->g(UploadManager::class)));
    // Set alias on the interface
    $di->alias(UploadHandlerInterface::class, UploadHandler::class);

    // Set a callback to process uploaded files in the incoming requests.
    $jaxon->callback()->before(function() use($di) {
        /** @var UploadHandler */
        $xUploadHandler = $di->g(UploadHandler::class);
        // The HTTP request
        $xRequest = $di->getRequest();
        if($xUploadHandler->canProcessRequest($xRequest))
        {
            $xUploadHandler->processRequest($xRequest);
        }
    });
}

/**
 * Register the values into the container
 *
 * @return void
 */
function _register(): void
{
    $jaxon = jaxon();
    $jaxon->callback()->boot(fn() => registerUpload());
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
