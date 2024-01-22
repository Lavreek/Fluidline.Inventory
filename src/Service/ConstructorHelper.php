<?php
namespace App\Service;

final class ConstructorHelper extends FileHelper
{
    const difference = ['..', '.'];

    private string $inventoryPath;

    public function setInventoryPath($path) : void
    {
        $this->inventoryPath = $path;
    }

    private function getInventoryPath() : string
    {
        return $this->inventoryPath;
    }

    public function getImages(mixed $inventoryParameters) : array
    {
        $images = [];
        $imagesPath = $this->getInventoryPath() ."/images";
        $files = array_diff(
            scandir($imagesPath), self::difference
        );

        foreach ($files as $file_index => $file_value) {
            $fileinfo = pathinfo($file_value);
            $files[$file_index] = $fileinfo['filename'];
        }

        foreach ($inventoryParameters as $parameter) {
            if (in_array($parameter['name'], $files)) {
                $fileKey = array_search($parameter['name'], $files);
                unset($files[$fileKey]);

                $file = fopen($imagesPath ."/". $parameter['name'] .".csv", 'r');

                $rowPosition = 0;
                $header = [];

                $delimiter = $this->getFileDelimiter($file);

                while ($row = fgetcsv($file, separator: $delimiter)) {
                    if ($rowPosition == 0) {
                        $header = $row;
                    } else {
                        $matchPosition = array_search('match', $header);
                        $resourcePosition = array_search('resource', $header);

                        if (preg_match('#' . $row[$matchPosition] . '#u', $parameter['value'])) {
                            $images[] = ['name' => $parameter['name'], 'resource' => $row[$resourcePosition]];
                        }
                    }

                    $rowPosition++;
                }
            }
        }

        foreach ($files as $file) {
            $file_resource = fopen($imagesPath ."/{$file}.csv", 'r');

            $rowPosition = 0;
            $header = [];

            $delimiter = $this->getFileDelimiter($file_resource);

            while ($row = fgetcsv($file_resource, separator: $delimiter)) {
                if ($rowPosition == 0) {
                    $header = $row;
                } else {
                    $matchPosition = array_search('match', $header);
                    $resourcePosition = array_search('resource', $header);

                    if (preg_match('#' . $row[$matchPosition] . '#u', $file)) {
                        $images[] = ['name' => $file, 'resource' => $row[$resourcePosition]];
                    }
                }

                $rowPosition++;
            }
        }

        return $images;
    }

    public function getElements() {
        $elements = [];
        $inputsPath = $this->getInventoryPath() ."/elements";

        $files = array_diff(
            scandir($inputsPath), self::difference
        );

        foreach ($files as $file) {
            $elements[] = json_decode(
                file_get_contents($inputsPath . "/$file"), true
            );
        }

        return $elements;
    }
}
