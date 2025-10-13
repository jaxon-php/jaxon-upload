<?php

namespace Jaxon\Upload\Tests\TestUpload;

use Jaxon\Jaxon;
use Jaxon\Exception\RequestException;
use Jaxon\Exception\SetupException;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

use function Jaxon\jaxon;
use function Jaxon\Upload\_register;
use function copy;
use function filesize;
use function mkdir;

class UploadFsLocalTest extends TestCase
{
    /**
     * @var string
     */
    protected $sNameWhite;

    /**
     * @var string
     */
    protected $sPathWhite;

    /**
     * @var int
     */
    protected $sSizeWhite;

    /**
     * @var string
     */
    protected $sNameBlue;

    /**
     * @var string
     */
    protected $sPathBlue;

    /**
     * @var int
     */
    protected $sSizeBlue;

    /**
     * @throws SetupException
     */
    public function setUp(): void
    {
        jaxon()->di()->getPluginManager()->registerPlugins();
        _register();
        jaxon()->setOption('core.response.send', false);

        $tmpDir = __DIR__ . '/../upload/tmp';
        @mkdir($tmpDir);

        $sSrcWhite = __DIR__ . '/../upload/src/white.png';
        $this->sNameWhite = 'white.png';
        $this->sPathWhite = "$tmpDir/{$this->sNameWhite}";
        $this->sSizeWhite = filesize($sSrcWhite);
        // Copy the file to the temp dir.
        @copy($sSrcWhite, $this->sPathWhite);

        $sSrcBlue = __DIR__ . '/../upload/src/blue.png';
        $this->sNameBlue = 'blue.png';
        $this->sPathBlue = "$tmpDir/{$this->sNameBlue}";
        $this->sSizeBlue = filesize($sSrcBlue);
        // Copy the file to the temp dir.
        @copy($sSrcBlue, $this->sPathBlue);

        jaxon()->register(Jaxon::CALLABLE_CLASS, 'SampleUpload', __DIR__ . '/../src/sample.php');
    }

    /**
     * @throws SetupException
     */
    public function tearDown(): void
    {
        jaxon()->reset();
        parent::tearDown();
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadDisabled()
    {
        jaxon()->setOption('core.upload.enabled', false);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertFalse(jaxon()->di()->getRequestHandler()->canProcessRequest());
    }

    /**
     * @throws RequestException
     */
    public function testRequestWithNoPluginNoUpload()
    {
        jaxon()->setOption('core.upload.enabled', false);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'who' => 'Nobody',
                        'args' => [],
                    ]),
                ])
                ->withMethod('POST');
        });

        $this->assertFalse(jaxon()->di()->getRequestHandler()->canProcessRequest());
    }
}
