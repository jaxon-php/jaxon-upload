<?php

/**
 * UploadHandler.php
 *
 * File upload with Ajax.
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2017 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload;

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Di\Container;
use Jaxon\Exception\RequestException;
use Jaxon\Request\Handler\UploadHandlerInterface;
use Jaxon\Response\Manager\ResponseManager;
use Jaxon\Upload\Manager\FileNameInterface;
use Jaxon\Upload\Manager\FileStorage;
use Jaxon\Upload\Manager\UploadManager;
use Jaxon\Upload\Manager\Validator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;

use Closure;
use Exception;

use function bin2hex;
use function count;
use function random_bytes;
use function trim;

class UploadHandler implements UploadHandlerInterface
{
    /**
     * The upload manager
     *
     * @var UploadManager
     */
    protected $xUploadManager;

    /**
     * The response manager
     *
     * @var ResponseManager
     */
    protected $xResponseManager;

    /**
     * @var Translator
     */
    protected $xTranslator;

    /**
     * The Psr17 factory
     *
     * @var Psr17Factory
     */
    protected $xPsr17Factory;

    /**
     * The uploaded files copied in the user dir
     *
     * @var array
     */
    protected $aUserFiles = [];

    /**
     * The name of file containing upload data
     *
     * @var string
     */
    protected $sTempFile = '';

    /**
     * Is the current request an HTTP upload
     *
     * @var bool
     */
    protected $bIsAjaxRequest = true;

    /**
     * The constructor
     *
     * @param UploadManager $xUploadManager
     * @param ResponseManager $xResponseManager
     * @param Translator $xTranslator
     * @param Psr17Factory $xPsr17Factory
     */
    public function __construct(UploadManager $xUploadManager, ResponseManager $xResponseManager,
        Translator $xTranslator, Psr17Factory $xPsr17Factory)
    {
        $this->xUploadManager = $xUploadManager;
        $this->xResponseManager = $xResponseManager;
        $this->xTranslator = $xTranslator;
        $this->xPsr17Factory = $xPsr17Factory;
    }

    /**
     * @param Container $di
     * @param bool $bForce Force registration
     *
     * @return void
     */
    public static function register(Container $di, bool $bForce = false)
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
     * Set the uploaded file name sanitizer
     *
     * @param Closure $cSanitizer    The closure
     *
     * @return void
     */
    public function sanitizer(Closure $cSanitizer)
    {
        $this->xUploadManager->setNameSanitizer($cSanitizer);
    }

    /**
     * Get the uploaded files
     *
     * @return array
     */
    public function files(): array
    {
        return $this->aUserFiles;
    }

    /**
     * Inform this plugin that other plugin can process the current request
     *
     * @return void
     */
    public function isHttpUpload()
    {
        $this->bIsAjaxRequest = false;
    }

    /**
     * Check if the current request contains uploaded files
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return bool
     */
    public function canProcessRequest(ServerRequestInterface $xRequest): bool
    {
        if(count($xRequest->getUploadedFiles()) > 0)
        {
            return true;
        }
        $aBody = $xRequest->getParsedBody();
        if(is_array($aBody))
        {
            return isset($aBody['jxnupl']);
        }
        $aParams = $xRequest->getQueryParams();
        return isset($aParams['jxnupl']);
    }

    /**
     * Read the upload temp file name from the HTTP request
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return bool
     */
    private function setTempFile(ServerRequestInterface $xRequest): bool
    {
        $aBody = $xRequest->getParsedBody();
        if(is_array($aBody))
        {
            $this->sTempFile = trim($aBody['jxnupl'] ?? '');
            return $this->sTempFile !== '';
        }
        $aParams = $xRequest->getQueryParams();
        $this->sTempFile = trim($aParams['jxnupl'] ?? '');
        return $this->sTempFile !== '';
    }

    /**
     * Process the uploaded files in the HTTP request
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return bool
     * @throws RequestException
     */
    public function processRequest(ServerRequestInterface $xRequest): bool
    {
        if($this->setTempFile($xRequest))
        {
            // Ajax request following a normal HTTP upload.
            // Copy the previously uploaded files' location from the temp file.
            $this->aUserFiles = $this->xUploadManager->readFromTempFile($this->sTempFile);
            return true;
        }

        if($this->bIsAjaxRequest)
        {
            // Ajax request with upload.
            // Copy the uploaded files from the HTTP request.
            $this->aUserFiles = $this->xUploadManager->readFromHttpData($xRequest);
            return true;
        }

        // For HTTP requests, save the files' location to a temp file,
        // and return a response with a reference to this temp file.
        try
        {
            // Copy the uploaded files from the HTTP request, and create the temp file.
            $this->aUserFiles = $this->xUploadManager->readFromHttpData($xRequest);
            $sTempFile = $this->xUploadManager->saveToTempFile($this->aUserFiles);
            $this->xResponseManager->append(new UploadResponse($this->xPsr17Factory, $sTempFile));
        }
        catch(Exception $e)
        {
            $this->xResponseManager->append(new UploadResponse($this->xPsr17Factory, '', $e->getMessage()));
        }
        return true;
    }
}
