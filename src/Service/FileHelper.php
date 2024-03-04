<?php
namespace App\Service;

class FileHelper
{
    public function getFileDelimiter($file) : bool|string
    {
        $line = fgets($file);
        rewind($file);

        preg_match("([\,|\;])", $line, $match);

        if (isset($match[0])) {
            return $match[0];
        }

        return false;
    }
}
