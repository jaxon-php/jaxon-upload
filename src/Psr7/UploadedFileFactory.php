<?php

declare(strict_types=1);

namespace Jaxon\Upload\Psr7;

use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 *
 * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
 */
class UploadedFileFactory implements UploadedFileFactoryInterface
{
    public function createUploadedFile(StreamInterface $stream, ?int $size = null,
        int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null,
        ?string $clientMediaType = null): UploadedFileInterface
    {
        if (null === $size) {
            $size = $stream->getSize();
        }

        /*
         * Change: return an instance of our custom UploadedFile class.
         */
        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
