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

use function call_user_func;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function realpath;
use function rtrim;
use function trim;

class UploadManager
{
    /**
     * @var ConfigManager
     */
    protected $xConfigManager;

    /**
     * The request data validator
     *
     * @var Validator
     */
    protected $xValidator;

    /**
     * @var Translator
     */
    protected $xTranslator;

    /**
     * The file and dir name generator
     *
     * @var FileNameInterface
     */
    protected $xFileName;

    /**
     * The file storage
     *
     * @var FileStorage
     */
    protected $xFileStorage;

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
     * @param FileNameInterface $xFileName
     * @param ConfigManager $xConfigManager
     * @param Validator $xValidator
     * @param Translator $xTranslator
     * @param FileStorage $xFileStorage
     */
    public function __construct(FileNameInterface $xFileName, ConfigManager $xConfigManager,
        Validator $xValidator, Translator $xTranslator, FileStorage $xFileStorage)
    {
        $this->xFileName = $xFileName;
        $this->xConfigManager = $xConfigManager;
        $this->xValidator = $xValidator;
        $this->xTranslator = $xTranslator;
        $this->xFileStorage = $xFileStorage;
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
        return $this->xFileName->random(14);
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
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
    }

    /**
     * Get the path to the upload dir
     *
     * @param string $sFieldId    The filename
     *
     * @return array
     * @throws RequestException
     */
    private function getUploadDir(string $sFieldId): array
    {
        $xFilesystem = $this->xFileStorage->filesystem($sFieldId);
        return [$xFilesystem, $this->_makeUploadDir($xFilesystem, $this->randomName())];
    }

    /**
     * Get the path to the upload temp dir
     *
     * @return array
     * @throws RequestException
     */
    private function getUploadTempDir(): array
    {
        $xFilesystem = $this->xFileStorage->filesystem();
        return [$xFilesystem, $this->_makeUploadDir($xFilesystem, 'tmp')];
    }

    /**
     * Check uploaded files
     *
     * @param Filesystem $xFilesystem
     * @param string $sUploadDir
     * @param string $sVarName
     * @param UploadedFile $xHttpFile
     *
     * @return File
     * @throws RequestException
     */
    private function makeUploadedFile(Filesystem $xFilesystem, string $sUploadDir, string $sVarName, UploadedFile $xHttpFile): File
    {
        // Filename without the extension. Needs to be sanitized.
        $sName = pathinfo($xHttpFile->getClientFilename(), PATHINFO_FILENAME);
        if($this->cNameSanitizer !== null)
        {
            $sName = (string)call_user_func($this->cNameSanitizer, $sName, $sVarName, $this->sUploadFieldId);
        }
        // Check the uploaded file validity
        if($xHttpFile->getError())
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.failed', ['name' => $sVarName]));
        }
        // Set the user file data
        $xFile = File::fromHttpFile($xFilesystem, $sUploadDir, $sName, $xHttpFile);
        // Verify file validity (format, size)
        if(!$this->xValidator->validateUploadedFile($sVarName, $xFile))
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
        foreach($aTempFiles as $sVarName => $aFiles)
        {
            $aUserFiles[$sVarName] = [];
            // Get the path to the upload dir
            [$xFilesystem, $sUploadDir] = $this->getUploadDir($sVarName);
            if(!is_array($aFiles))
            {
                $aFiles = [$aFiles];
            }
            foreach($aFiles as $xHttpFile)
            {
                $aUserFiles[$sVarName][] = $this->makeUploadedFile($xFilesystem, $sUploadDir, $sVarName, $xHttpFile);
            }
        }
        // Copy the uploaded files from the temp dir to the user dir
        try
        {
            foreach($this->aAllFiles as $aFiles)
            {
                $aFiles['user']->filesystem()->write($aFiles['user']->path(), $aFiles['temp']->getStream()->getContents());
            }
        }
        catch(FilesystemException $e)
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
        return $aUserFiles;
    }

    /**
     * Save uploaded files info to a temp file
     *
     * @param array $aUserFiles
     *
     * @return string
     * @throws RequestException
     */
    public function saveToTempFile(array $aUserFiles): string
    {
        // Convert uploaded file to an array
        $aFiles = [];
        foreach($aUserFiles as $sVarName => $aVarFiles)
        {
            $aFiles[$sVarName] = [];
            foreach($aVarFiles as $aVarFile)
            {
                $aFiles[$sVarName][] = $aVarFile->toTempData();
            }
        }
        // Save upload data in a temp file
        [$xFilesystem, $sUploadDir] = $this->getUploadTempDir();
        $sTempFile = $this->randomName();
        try
        {
            $xFilesystem->write($sUploadDir . '/' . $sTempFile . '.json', json_encode($aFiles));
        }
        catch(FilesystemException $e)
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
        return $sTempFile;
    }

    /**
     * Get the path to the upload temp file
     *
     * @param string $sTempFile
     *
     * @return array
     * @throws RequestException
     */
    private function getUploadTempFile(string $sTempFile): array
    {
        // Verify file name validity
        if(!$this->xValidator->validateTempFileName($sTempFile))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.invalid'));
        }
        [$xFilesystem, $sUploadDir] = $this->getUploadTempDir();
        $sUploadTempFile = $sUploadDir . '/' . $sTempFile . '.json';
        try
        {
            if($xFilesystem->visibility($sUploadTempFile) !== Visibility::PUBLIC)
            {
                throw new RequestException($this->xTranslator->trans('errors.upload.access'));
            }
            return [$xFilesystem, $sUploadTempFile];
        }
        catch(FilesystemException $e)
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
    }

    /**
     * Read uploaded files info from a temp file
     *
     * @param string $sTempFile
     *
     * @return array
     * @throws RequestException
     */
    public function readFromTempFile(string $sTempFile): array
    {
        // Upload temp file
        [$xFilesystem, $sUploadTempFile] = $this->getUploadTempFile($sTempFile);
        try
        {
            $aFiles = json_decode($xFilesystem->read($sUploadTempFile), true);
        }
        catch(FilesystemException $e)
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
        $aUserFiles = [];
        foreach($aFiles as $sVarName => $aVarFiles)
        {
            $aUserFiles[$sVarName] = [];
            foreach($aVarFiles as $aVarFile)
            {
                $aUserFiles[$sVarName][] = File::fromTempFile($xFilesystem, $aVarFile);
            }
        }
        try
        {
            $xFilesystem->delete($sUploadTempFile);
        }
        catch(FilesystemException $e){}
        return $aUserFiles;
    }
}
