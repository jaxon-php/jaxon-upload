<?php

/**
 * FileStorage.php
 *
 * Manage storage systems for uploaded files.
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
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

use Closure;

use function call_user_func;
use function is_string;
use function trim;

class FileStorage
{
    /**
     * @var ConfigManager
     */
    protected $xConfigManager;

    /**
     * @var Translator
     */
    protected $xTranslator;

    /**
     * @var array
     */
    protected $aAdapters = [];

    /**
     * @var array
     */
    protected $aFilesystems = [];

    /**
     * The constructor
     *
     * @param ConfigManager $xConfigManager
     * @param Translator $xTranslator
     */
    public function __construct(ConfigManager $xConfigManager, Translator $xTranslator)
    {
        $this->xConfigManager = $xConfigManager;
        $this->xTranslator = $xTranslator;
    }

    /**
     * @param string $sStorage
     * @param Closure $cFactory
     *
     * @return void
     */
    public function registerAdapter(string $sStorage, Closure $cFactory)
    {
        $this->aAdapters[$sStorage] = $cFactory;
    }

    /**
     * Register the file storage adapters
     *
     * @return void
     */
    public function registerAdapters()
    {
        // Local file system adapter
        $this->registerAdapter('local', function(string $sRootDir, $xOptions) {
            return empty($xOptions) ? new LocalFilesystemAdapter($sRootDir) :
                new LocalFilesystemAdapter($sRootDir, $xOptions);
        });

        // In memory file system adapter
        $this->registerAdapter('memory', function() {
            return new \League\Flysystem\InMemory\InMemoryFilesystemAdapter();
        });

        // AWS S3 file system adapter
        $this->registerAdapter('aws-s3', function(string $sRootDir, array $aOptions) {
            /** @var \Aws\S3\S3ClientInterface $client */
            $client = new \Aws\S3\S3Client($aOptions['client'] ?? []);
            return new \League\Flysystem\AwsS3V3\AwsS3V3Adapter($client, $aOptions['bucket'] ?? '', $sRootDir);
        });

        // Async AWS S3 file system adapter
        $this->registerAdapter('async-aws-s3', function(string $sRootDir, array $aOptions) {
            $client = isset($aOptions['client']) ? new \AsyncAws\S3\S3Client($aOptions['client']) : new \AsyncAws\S3\S3Client();
            return new \League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter($client, $aOptions['bucket'] ?? '', $sRootDir);
        });

        // Google Cloud file system adapter
        $this->registerAdapter('google-cloud', function(string $sRootDir, array $aOptions) {
            $storageClient = new \Google\Cloud\Storage\StorageClient($aOptions['client'] ?? []);
            $bucket = $storageClient->bucket($aOptions['bucket'] ?? '');
            return new \League\Flysystem\AzureBlobStorage\GoogleCloudStorageAdapter($bucket, $sRootDir);
        });

        // Microsoft Azure file system adapter
        $this->registerAdapter('azure-blob', function(string $sRootDir, array $aOptions) {
            $client = \MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService($aOptions['dsn']);
            return new \League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter($client, $aOptions['container'], $sRootDir);
        });

        // FTP file system adapter
        $this->registerAdapter('ftp', function(string $sRootDir, array $aOptions) {
            $aOptions['root'] = $sRootDir;
            $xOptions = \League\Flysystem\Ftp\FtpConnectionOptions::fromArray($aOptions);
            return new \League\Flysystem\Ftp\FtpAdapter($xOptions);
        });

        // SFTP V2 file system adapter
        $this->registerAdapter('sftp-v2', function(string $sRootDir, array $aOptions) {
            $provider = new \League\Flysystem\PhpseclibV2\SftpConnectionProvider(...$aOptions);
            return new \League\Flysystem\PhpseclibV2\SftpAdapter($provider, $sRootDir);
        });

        // SFTP V3 file system adapter
        $this->registerAdapter('sftp-v3', function(string $sRootDir, array $aOptions) {
            $provider = new \League\Flysystem\PhpseclibV3\SftpConnectionProvider(...$aOptions);
            return new \League\Flysystem\PhpseclibV3\SftpAdapter($provider, $sRootDir);
        });
    }

    /**
     * @param string $sField
     *
     * @return Filesystem
     * @throws RequestException
     */
    public function filesystem(string $sField = ''): Filesystem
    {
        $sField = trim($sField);
        if(isset($this->aFilesystems[$sField]))
        {
            return $this->aFilesystems[$sField];
        }

        // Default upload dir
        $sStorage = $this->xConfigManager->getOption('upload.default.storage', 'local');
        $sRootDir = $this->xConfigManager->getOption('upload.default.dir', '');
        $aOptions = $this->xConfigManager->getOption('upload.default.options');
        $sConfigKey = "upload.files.$sField";
        if($sField !== '' && $this->xConfigManager->hasOption($sConfigKey))
        {
            $sStorage = $this->xConfigManager->getOption("$sConfigKey.storage", $sStorage);
            $sRootDir = $this->xConfigManager->getOption("$sConfigKey.dir", $sRootDir);
            $aOptions = $this->xConfigManager->getOption("$sConfigKey.options", $aOptions);
        }

        if(!is_string($sRootDir))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.dir'));
        }
        if(!isset($this->aAdapters[$sStorage]))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.adapter'));
        }

        $this->aFilesystems[$sField] = new Filesystem(call_user_func($this->aAdapters[$sStorage], $sRootDir, $aOptions));
        return $this->aFilesystems[$sField];
    }
}
