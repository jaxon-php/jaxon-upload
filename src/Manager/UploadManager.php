<?php

/**
 * UploadManager.php
 *
 * This class processes uploaded files.
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2022 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload\Manager;

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Exception\RequestException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Visibility;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

use Closure;

use function Jaxon\jaxon;
use function call_user_func;
use function is_array;

class UploadManager
{
    /**
     * The id of the upload field in the form
     *
     * @var string
     */
    protected $sUploadFieldId = '';

    /**
     * A user defined function to transform uploaded file names
     *
     * @var Closure
     */
    protected $cNameSanitizer = null;

    /**
     * A flat list of all uploaded files
     *
     * @var array
     */
    private $aAllFiles = [];

    /**
     * The constructor
     *
     * @param FileStorage $xFileStorage
     * @param FileNameInterface $xFileName
     * @param ConfigManager $xConfigManager
     * @param Validator $xValidator
     * @param Translator $xTranslator
     */
    public function __construct(private FileStorage $xFileStorage,
        private FileNameInterface $xFileName, private ConfigManager $xConfigManager,
        private Validator $xValidator, private Translator $xTranslator)
    {
        // This feature is not yet implemented
        $this->setUploadFieldId('');
    }

    /**
     * Generate a random name
     *
     * @return string
     */
    protected function randomName(): string
    {
        return $this->xFileName->random(16);
    }

    /**
     * Set the id of the upload field in the form
     *
     * @param string $sUploadFieldId
     *
     * @return void
     */
    public function setUploadFieldId(string $sUploadFieldId)
    {
        $this->sUploadFieldId = $sUploadFieldId;
    }

    /**
     * Filter uploaded file name
     *
     * @param Closure $cNameSanitizer    The closure which filters filenames
     *
     * @return void
     */
    public function setNameSanitizer(Closure $cNameSanitizer)
    {
        $this->cNameSanitizer = $cNameSanitizer;
    }

    /**
     * Make sure the upload dir exists and is writable
     *
     * @param Filesystem $xFilesystem
     * @param string $sUploadDir
     *
     * @return string
     * @throws RequestException
     */
    private function _makeUploadDir(Filesystem $xFilesystem, string $sUploadDir): string
    {
        try
        {
            $xFilesystem->createDirectory($sUploadDir);
            if($xFilesystem->visibility($sUploadDir) !== Visibility::PUBLIC)
            {
                throw new RequestException($this->xTranslator->trans('errors.upload.access'));
            }
            return $sUploadDir;
        }
        catch(FilesystemException $e)
        {
            jaxon()->logger()->error('Filesystem error', ['message' => $e->getMessage()]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
    }

    /**
     * Get the path to the upload dir
     *
     * @param string $sField
     *
     * @return string
     * @throws RequestException
     */
    private function getUploadDir(string $sField): string
    {
        return $this->_makeUploadDir($this->xFileStorage->filesystem($sField), $this->randomName() . '/');
    }

    /**
     * Check uploaded files
     *
     * @param UploadedFile $xHttpFile
     * @param string $sUploadDir
     * @param string $sField
     *
     * @return File
     * @throws RequestException
     */
    private function makeUploadedFile(UploadedFile $xHttpFile, string $sUploadDir, string $sField): File
    {
        // Check the uploaded file validity
        if($xHttpFile->getError())
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.failed', ['name' => $sField]));
        }

        // Filename without the extension. Needs to be sanitized.
        $sName = pathinfo($xHttpFile->getClientFilename(), PATHINFO_FILENAME);
        if($this->cNameSanitizer !== null)
        {
            $sName = (string)call_user_func($this->cNameSanitizer, $sName, $sField, $this->sUploadFieldId);
        }

        // Set the user file data
        $xFile = File::fromHttpFile($this->xFileStorage->filesystem($sField), $xHttpFile, $sUploadDir, $sName);
        // Verify file validity (format, size)
        if(!$this->xValidator->validateUploadedFile($sField, $xFile))
        {
            throw new RequestException($this->xValidator->getErrorMessage());
        }

        // All's right, save the file for copy.
        $this->aAllFiles[] = ['temp' => $xHttpFile, 'user' => $xFile];
        return $xFile;
    }

    /**
     * Read uploaded files info from HTTP request data
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return array
     * @throws RequestException
     */
    public function readFromHttpData(ServerRequestInterface $xRequest): array
    {
        // Get the uploaded files
        $aTempFiles = $xRequest->getUploadedFiles();

        $aUserFiles = [];
        $this->aAllFiles = []; // A flat list of all uploaded files
        foreach($aTempFiles as $sField => $aFiles)
        {
            $aUserFiles[$sField] = [];
            // Get the path to the upload dir
            $sUploadDir = $this->getUploadDir($sField);
            if(!is_array($aFiles))
            {
                $aFiles = [$aFiles];
            }
            foreach($aFiles as $xHttpFile)
            {
                $aUserFiles[$sField][] = $this->makeUploadedFile($xHttpFile, $sUploadDir, $sField);
            }
        }
        // Copy the uploaded files from the temp dir to the user dir
        try
        {
            foreach($this->aAllFiles as $aFiles)
            {
                $aFiles['user']->filesystem()->write($aFiles['user']->path(), $aFiles['temp']->getStream());
            }
        }
        catch(FilesystemException $e)
        {
            jaxon()->logger()->error('Filesystem error', ['message' => $e->getMessage()]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
        return $aUserFiles;
    }
}
