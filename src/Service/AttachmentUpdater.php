<?php

namespace App\Service;

final class AttachmentUpdater
{
    private string $filepath;

    private string $serial;

    public function __construct(string $serial = "", string $path)
    {
        $this->setFilepath($path);
        $this->setSerial($serial);
    }

    private function setFilepath($path) : void
    {
        $this->filepath = $path;
    }

    public function getFilepath()
    {
        return $this->filepath;
    }

    private function setSerial($serial) : void
    {
        $this->serial = $serial;
    }

    public function getSerial()
    {
        return $this->serial;
    }

    public function updateAttachments(string $fileContent, string $column)
    {
        $filepath = $this->getFilepath();
        $serial = $this->getSerial();
        $file = fopen($filepath . $serial . ".csv", 'r');

        $search = ["\r"];
        $fileContent = explode("\n", str_replace($search, '', $fileContent));
        $newContent = "";

        $row = 0;
        while ($data = fgetcsv($file, separator: ';')) {
            if ($row > 0) {
                $uploadedHeader = [];

                foreach ($fileContent as $rowIndex => $uploadedRow) {
                    if ($rowIndex < 1) {
                        $uploadedHeader = explode(';', $uploadedRow);

                    } else {
                        if (in_array('code_id', $uploadedHeader)) {
                            $code_id = array_search('code_id', $uploadedHeader);
                            $uploadedRow = explode(';', $uploadedRow);

                            if (count($uploadedRow) == count($uploadedHeader)) {

                                if ($data[1] == $uploadedRow[$code_id]) {
                                    $attachment = array_search($column, $uploadedHeader);
                                    $data[2] = $uploadedRow[$attachment];
                                }
                            }
                        } elseif (in_array('code', $uploadedHeader)) {
                            $code = array_search('code', $uploadedHeader);
                            $uploadedRow = explode(';', $uploadedRow);

                            if (count($uploadedRow) == count($uploadedHeader)) {
                                if ($data[0] == $uploadedRow[$code]) {
                                    $attachment = array_search($column, $uploadedHeader);
                                    $data[2] = $uploadedRow[$attachment];
                                }
                            }
                        }
                    }
                }
            }

            $newContent .= implode(';', $data) ."\n";

            $row++;
        }

//        fwrite($file, $newContent);

        dd($newContent, $fileContent);



    }
}