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

use Jaxon\App\I18n\Translator;
use Jaxon\Exception\RequestException;
use Jaxon\Plugin\Manager\PluginManager;
use Jaxon\Request\Upload\UploadHandlerInterface;
use Jaxon\Response\ResponseManager;
use Jaxon\Upload\Manager\FileStorage;
use Jaxon\Upload\Manager\UploadManager;
use Psr\Http\Message\ServerRequestInterface;
use Closure;

use function count;

class UploadHandler implements UploadHandlerInterface
{
    /**
     * The upload manager
     *
     * @var UploadManager
     */
    protected $xUploadManager;

    /**
     * The file storage
     *
     * @var FileStorage
     */
    protected $xFileStorage;

    /**
     * The response manager
     *
     * @var ResponseManager
     */
    protected $xResponseManager;

    /**
     * @var PluginManager
     */
    protected $xPluginManager;

    /**
     * @var Translator
     */
    protected $xTranslator;

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
     * @param FileStorage $xFileStorage
     * @param ResponseManager $xResponseManager
     * @param PluginManager $xPluginManager
     * @param Translator $xTranslator
     */
    public function __construct(UploadManager $xUploadManager, FileStorage $xFileStorage,
        ResponseManager $xResponseManager, PluginManager $xPluginManager, Translator $xTranslator)
    {
        $this->xUploadManager = $xUploadManager;
        $this->xFileStorage = $xFileStorage;
        $this->xResponseManager = $xResponseManager;
        $this->xPluginManager = $xPluginManager;
        $this->xTranslator = $xTranslator;
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
     * Check if the current request contains uploaded files
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return bool
     */
    public function canProcessRequest(ServerRequestInterface $xRequest): bool
    {
        return count($xRequest->getUploadedFiles()) > 0;
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
        // Ajax request with upload.
        // Copy the uploaded files from the HTTP request.
        $this->aUserFiles = $this->xUploadManager->readFromHttpData($xRequest);
        return true;
    }

    /**
     * @param string $sStorage
     * @param Closure $cFactory
     *
     * @return void
     */
    public function registerStorageAdapter(string $sStorage, Closure $cFactory)
    {
        $this->xFileStorage->registerAdapter($sStorage, $cFactory);
    }
}
