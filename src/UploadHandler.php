<?php

/**
 * UploadHandler.php
 *
 * File upload with Ajax.
 *
 * @package jaxon-upload
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2017 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload;

use Jaxon\Exception\RequestException;
use Jaxon\Request\Upload\UploadHandlerInterface;
use Jaxon\Upload\Manager\FileStorage;
use Jaxon\Upload\Manager\UploadManager;
use Psr\Http\Message\ServerRequestInterface;
use Closure;

use function count;

class UploadHandler implements UploadHandlerInterface
{
    /**
     * The uploaded files copied in the user dir
     *
     * @var array
     */
    private $aUserFiles = [];

    /**
     * The constructor
     *
     * @param FileStorage $xFileStorage
     * @param UploadManager $xUploadManager
     */
    public function __construct(private FileStorage $xFileStorage,
        private UploadManager $xUploadManager)
    {}

    /**
     * Set the uploaded file name sanitizer
     *
     * @param Closure $cSanitizer    The closure
     *
     * @return void
     */
    public function sanitizer(Closure $cSanitizer): void
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
    public function registerStorageAdapter(string $sStorage, Closure $cFactory): void
    {
        $this->xFileStorage->registerAdapter($sStorage, $cFactory);
    }
}
