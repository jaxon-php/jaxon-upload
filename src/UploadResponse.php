<?php

namespace Jaxon\Upload;

use Jaxon\Response\AbstractResponse;
use Jaxon\Response\ResponseManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

use function addslashes;
use function array_reduce;
use function json_encode;

class UploadResponse extends AbstractResponse
{
    /**
     * The path to the uploaded file
     *
     * @var string
     */
    private $sUploadedFile = '';

    /**
     * The constructor
     *
     * @param ResponseManager $xManager
     * @param Psr17Factory $xPsr17Factory
     * @param string $sUploadedFile
     */
    public function __construct(ResponseManager $xManager, Psr17Factory $xPsr17Factory,
        string $sUploadedFile = '')
    {
        parent::__construct($xManager, $xPsr17Factory);
        $this->sUploadedFile = $sUploadedFile;
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return 'text/html';
    }

    /**
     * Get the path to the uploaded file
     *
     * @return string
     */
    public function getUploadedFile(): string
    {
        return $this->sUploadedFile;
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): string
    {
        $aResult = ($this->sUploadedFile) ?
            ['code' => 'success', 'upl' => $this->sUploadedFile] :
            ['code' => 'error', 'msg' => $this->getErrorMessage()];
        $aMessages = $this->xManager->getDebugMessages();
        $sMessages = array_reduce($aMessages, function($sJsLog, $sMessage) {
            return "$sJsLog\n\t" . 'console.log("' . addslashes($sMessage) . '");';
        }, '');

        return '
<!DOCTYPE html>
<html>
<body>
<h1>HTTP Upload for Jaxon</h1>
</body>
<script>
    res = ' . json_encode($aResult) . ';
    // Debug messages ' . $sMessages . '
</script>
</html>';
    }

    /**
     * Convert this response to a PSR7 response object
     *
     * @return PsrResponseInterface
     */
    public function toPsr(): PsrResponseInterface
    {
        return $this->xPsr17Factory->createResponse(($this->sUploadedFile) ? 200 : 500)
            ->withHeader('content-type', $this->getContentType())
            ->withBody(Stream::create($this->getOutput()));
    }
}
