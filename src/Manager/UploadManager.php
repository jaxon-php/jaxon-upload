<?php

/**
 * UploadManager.php
 *
 * This class processes uploaded files.
 *
 * @package jaxon-upload
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2022 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload\Manager;

use Jaxon\App\I18n\Translator;
use Jaxon\Exception\RequestException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Closure;

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
     * @var array
     */
    private $errorMessages = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];

    /**
     * The constructor
     *
     * @param LoggerInterface $xLogger
     * @param Validator $xValidator
     * @param Translator $xTranslator
     * @param FileStorage $xFileStorage
     * @param FileNameInterface $xFileName
     */
    public function __construct(private LoggerInterface $xLogger,
        private Validator $xValidator, private Translator $xTranslator,
        private FileStorage $xFileStorage, private FileNameInterface $xFileName)
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
    public function setUploadFieldId(string $sUploadFieldId): void
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
    public function setNameSanitizer(Closure $cNameSanitizer): void
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
            if(!$xFilesystem->directoryExists($sUploadDir))
            {
                throw new RequestException($this->xTranslator->trans('errors.upload.access'));
            }
            return $sUploadDir;
        }
        catch(FilesystemException $e)
        {
            $this->xLogger->error('Filesystem error.', ['message' => $e->getMessage()]);
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
        $xFileSystem = $this->xFileStorage->filesystem($sField);
        return $this->_makeUploadDir($xFileSystem, $this->randomName() . '/');
    }

    /**
     * Check uploaded files
     *
     * @param UploadedFile $xHttpFile
     * @param string $sUploadDir
     * @param string $sField
     *
     * @return array
     * @throws RequestException
     */
    private function makeUploadedFile(UploadedFile $xHttpFile, string $sUploadDir, string $sField): array
    {
        // Check the uploaded file validity
        $nErrorCode = $xHttpFile->getError();
        if($nErrorCode !== UPLOAD_ERR_OK)
        {
            $this->xLogger->error('File upload error.', [
                'code' => $nErrorCode,
                'message' => $this->errorMessages[$nErrorCode],
            ]);
            $sMessage = $this->xTranslator->trans('errors.upload.failed', [
                'name' => $sField,
            ]);
            throw new RequestException($sMessage);
        }

        // Filename without the extension. Needs to be sanitized.
        $sName = pathinfo($xHttpFile->getClientFilename(), PATHINFO_FILENAME);
        if($this->cNameSanitizer !== null)
        {
            $sName = (string)call_user_func($this->cNameSanitizer,
                $sName, $sField, $this->sUploadFieldId);
        }

        // Set the user file data
        $xFile = File::fromHttpFile($this->xFileStorage->filesystem($sField), $xHttpFile, $sUploadDir, $sName);
        // Verify file validity (format, size)
        if(!$this->xValidator->validateUploadedFile($sField, $xFile))
        {
            throw new RequestException($this->xValidator->getErrorMessage());
        }

        // All's right, save the file for copy.
        return ['temp' => $xHttpFile, 'user' => $xFile];
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
        $aAllFiles = []; // A flat list of all uploaded files
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
                $aFile = $this->makeUploadedFile($xHttpFile, $sUploadDir, $sField);
                $aUserFiles[$sField][] = $aFile['user'];
                $aAllFiles[] = $aFile;
            }
        }

        // Copy the uploaded files from the temp dir to the user dir
        try
        {
            foreach($aAllFiles as $aFiles)
            {
                $sPath = $aFiles['user']->path();
                $xContent = $aFiles['temp']->getStream();
                $aFiles['user']->filesystem()->write($sPath, $xContent);
            }
        }
        catch(FilesystemException $e)
        {
            $this->xLogger->error('Filesystem error.', ['message' => $e->getMessage()]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }

        return $aUserFiles;
    }
}
