<?php

namespace Jaxon\Upload\Manager;

interface NameGeneratorInterface
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
