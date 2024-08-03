<?php

namespace Jaxon\Upload\Manager;

interface FileNameInterface
{
    /**
     * Generate a random name for a file or dir
     *
     * @param int $nLength
     *
     * @return string
     */
    public function random(int $nLength): string;
}
