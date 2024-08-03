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
use Exception;

use function count;
use function is_array;
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
            $this->xResponseManager->setResponse(new UploadResponse($this->xResponseManager,
                $this->xPluginManager, $sTempFile));
        }
        catch(Exception $e)
        {
            $this->xResponseManager->setErrorMessage($e->getMessage());
            $this->xResponseManager->setResponse(new UploadResponse($this->xResponseManager,
                $this->xPluginManager));
        }
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
