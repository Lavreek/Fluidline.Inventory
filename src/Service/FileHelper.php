<?php
namespace App\Service;

class FileHelper
{
    public function getFileDelimiter($file) : bool|string
    {
        $delimiter = false;
        $tries = 0;

        while (!$delimiter) {
            $prev = stream_get_contents($file, 1);

            if ($prev == '#') {
                $delimiter = stream_get_contents($file, 1);
            }

            $tries ++;

            if ($tries > 10) {
                break;
            }
        }

        rewind($file);

        return $delimiter;
    }
}
