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

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Exception\RequestException;
use Jaxon\Storage\StorageManager;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Closure;

use function array_map;
use function fclose;
use function is_array;
use function is_resource;
use function is_string;
use function pathinfo;
use function rtrim;
use function trim;

class UploadManager
{
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
     * @param Validator $xValidator
     * @param Translator $xTranslator
     * @param LoggerInterface $xLogger
     * @param FileNameInterface $xFileName
     * @param StorageManager $xStorageManager
     * @param ConfigManager $xConfigManager
     */
    public function __construct(private Validator $xValidator, private Translator $xTranslator,
        private LoggerInterface $xLogger, private FileNameInterface $xFileName,
        private StorageManager $xStorageManager, private ConfigManager $xConfigManager)
    {}

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
     * @param string $sField
     * @param string $sOption
     * @param mixed $xDefault
     *
     * @return mixed
     */
    private function uploadOption(string $sField, string $sOption, mixed $xDefault): mixed
    {
        return $this->xConfigManager->getAppOption("upload.files.$sField.$sOption",
            $this->xConfigManager->getAppOption("upload.default.$sOption", $xDefault));
    }

    /**
     * Verify the uploaded file validity (format, size)
     *
     * @param string $sField
     * @param File $xFile
     *
     * @return void
     * @throws RequestException
     */
    private function validate(string $sField, File $xFile): void
    {
        if(!$this->xValidator->validateUploadedFile($sField, $xFile))
        {
            throw new RequestException($this->xValidator->getErrorMessage());
        }
    }

    /**
     * Copy the uploaded files from the temp dir to the user dir
     *
     * @param string $sField
     * @param UploadedFileInterface $xTempFile
     * @param File $xFile
     *
     * @return bool
     * @throws RequestException
     */
    private function move(string $sField, UploadedFileInterface $xTempFile, File $xFile): bool
    {
        if(!(bool)$this->uploadOption($sField, 'move', true))
        {
            return false;
        }

        $sDirectory = $this->uploadOption($sField, 'directory', null);
        if(is_string($sDirectory))
        {
            $xTempFile->moveTo(rtrim($sDirectory, '/') . '/' . $xFile->path());
            return true;
        }

        if(($resource = $xTempFile->getStream()->detach()) === null)
        {
            $this->xLogger->error('File upload error.', [
                'message' => "Unable to get the resource for the '$sField' uploaded file.",
            ]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
        try
        {
            $xFile->filesystem()->writeStream($xFile->path(), $resource);
            // Some FlySystem SDK’s close streams after consuming them.
            // See: https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/
            if(is_resource($resource))
            {
                fclose($resource);
            }
            return true;
        }
        catch(FilesystemException $e)
        {
            $this->xLogger->error('Filesystem error.', ['message' => $e->getMessage()]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }
    }

    /**
     * Check uploaded files
     *
     * @param Filesystem $xFilesystem
     * @param UploadedFileInterface $xTempFile
     * @param string $sUploadDir
     * @param string $sField
     *
     * @return File
     * @throws RequestException
     */
    private function file(Filesystem $xFilesystem, UploadedFileInterface $xTempFile,
        string $sUploadDir, string $sField): File
    {
        // Check the uploaded file validity
        $nErrorCode = $xTempFile->getError();
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
        $sName = pathinfo($xTempFile->getClientFilename(), PATHINFO_FILENAME);
        if($this->cNameSanitizer !== null)
        {
            $sName = (string)($this->cNameSanitizer)($sName, $sField);
        }

        // Set the user file data
        return File::fromHttpFile($xFilesystem, $sUploadDir, $sName, $xTempFile,
            fn(File $xFile) => $this->validate($sField, $xFile),
            fn(File $xFile) => $this->move($sField, $xTempFile, $xFile));
    }

    /**
     * @param string $sField
     *
     * @return Filesystem
     * @throws RequestException
     */
    private function storage(string $sField = ''): Filesystem
    {
        $sDirectory = $this->uploadOption($sField, 'directory', null);
        if(is_string($sDirectory))
        {
            return $this->xStorageManager->adapter('local')->make($sDirectory);
        }

        $sStorage = $this->uploadOption($sField, 'storage', null);
        if(!is_string($sStorage))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.adapter'));
        }

        return $this->xStorageManager->get($sStorage);
    }

    /**
     * Make sure the upload dir exists and is writable
     *
     * @param Filesystem $xFilesystem
     * @param string $sField
     *
     * @return Closure
     * @throws RequestException
     */
    private function processor(Filesystem $xFilesystem, string $sField): Closure
    {
        $sUploadDir = $this->xFileName->random(16) . '/';
        try
        {
            if(!$xFilesystem->directoryExists($sUploadDir))
            {
                $xFilesystem->createDirectory($sUploadDir);
            }
            if(!$xFilesystem->directoryExists($sUploadDir))
            {
                throw new RequestException($this->xTranslator->trans('errors.upload.access'));
            }
        }
        catch(FilesystemException $e)
        {
            $this->xLogger->error('Filesystem error.', ['message' => $e->getMessage()]);
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }

        return fn(UploadedFileInterface $xTempFile) =>
            $this->file($xFilesystem, $xTempFile, $sUploadDir, $sField);
    }

    /**
     * Read uploaded files info from HTTP request data
     *
     * @param ServerRequestInterface $xRequest
     *
     * @return array<string, File>
     * @throws RequestException
     */
    public function readFromHttpData(ServerRequestInterface $xRequest): array
    {
        // Get the uploaded files
        $aUserFiles = [];
        foreach($xRequest->getUploadedFiles() as $sField => $aFiles)
        {
            $sField = trim($sField);
            // Get the path to the upload dir
            $aUserFiles[$sField] = array_map(
                $this->processor($this->storage($sField), $sField),
                is_array($aFiles) ? $aFiles : [$aFiles]
            );
        }
        return $aUserFiles;
    }
}
