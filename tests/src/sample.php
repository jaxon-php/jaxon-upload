<?php

use function Jaxon\jaxon;

class SampleUpload
{
    public function myMethod()
    {
        $xResponse = jaxon()->getResponse();
        $xResponse->alert('This is a response!!');
    }
}
