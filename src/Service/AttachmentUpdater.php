<?php

namespace App\Service;

use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AttachmentUpdater
{
    private string $filepath;

    private string $serial;

    private array $search = ["\r"];

    public function __construct() { }

    public function setFilepath($path) : void
    {
        $this->filepath = $path;
    }

    public function getFilepath() : string|null
    {
        return $this->filepath;
    }

    public function setSerial($serial) : void
    {
        $this->serial = $serial;
    }

    public function getSerial() : string|null
    {
        return $this->serial;
    }

    public function updateAttachments(string $fileContent, string $column) : string
    {
        $filepath = $this->getFilepath();
        $serial = $this->getSerial();
        $file = fopen($filepath . $serial . ".csv", 'r');

        $fileContent = explode("\n", str_replace($this->search, '', $fileContent));

        $newContent =
        $cronContent = "";

        $code_id =
        $code = false;

        $row = 0;
        while ($data = fgetcsv($file, separator: ';')) {
            if ($row > 0) {
                $uploadedHeader = [];

                foreach ($fileContent as $rowIndex => $uploadedRow) {
                    $uploadedRow = explode(';', $uploadedRow);

                    if ($rowIndex === 0) {
                        $uploadedHeader = $uploadedRow;

                        if (in_array('code_id', $uploadedRow)) {
                            $code_id = array_search('code_id', $uploadedRow);
                        }

                        if (in_array('code', $uploadedRow)) {
                            $code = array_search('code', $uploadedRow);
                        }
                    } else {

                        if ($code_id !== false and $code === false) {
                            if (count($uploadedRow) == count($uploadedHeader)) {
                                if ($data[1] == $uploadedRow[$code_id]) {
                                    $attachment = array_search($column, $uploadedHeader);
                                    $data[2] = $uploadedRow[$attachment];
                                    $cronContent .= "{$data[0]};{$data[2]}\n";
                                }
                            }
                        } elseif ($code !== false and $code_id === false) {
                            if (count($uploadedRow) == count($uploadedHeader)) {
                                if ($data[0] == $uploadedRow[$code]) {
                                    $attachment = array_search($column, $uploadedHeader);
                                    $data[2] = $uploadedRow[$attachment];

                                    $cronContent .= "{$data[0]};{$data[2]}\n";
                                }
                            }
                        } elseif ($code_id !== false and $code !== false) {
                            if (count($uploadedRow) == count($uploadedHeader)) {
                                if ($data[0] == $uploadedRow[$code]) {
                                    $attachment = array_search($column, $uploadedHeader);
                                    $data[2] = $uploadedRow[$attachment];

                                    $cronContent .= "{$data[$code]};{$data[$code_id]};{$data[2]}\n";
                                }
                            }
                        }
                    }
                }
            }

            $newContent .= implode(';', $data) ."\n";

            $row++;
        }
        fclose($file);

        file_put_contents($filepath . $serial . ".csv", $newContent);

        if ($code !== false and $code_id !== false) {
            $cronContent = "code;code_id;$column\n$cronContent";

        }
        if ($code_id !== false and $code === false) {
            $cronContent = "code_id;$column\n$cronContent";
        }

        if ($code !== false and $code_id === false) {
            $cronContent = "code;$column\n$cronContent";
        }

        return $cronContent;
    }

    public function updateEntity(ManagerRegistry $registry, string $fileContent, string $column, string $entityColumn)
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $fileContent = explode("\n", str_replace($this->search, '', $fileContent));

        $codeIndex = null;
        // $codeIdIndex = null;
        $columnIndex = null;

        $entities = [];
        $updatedHeader = [];

        foreach ($fileContent as $rowIndex => $updatedRow) {
            $updatedRow = explode(';', $updatedRow);

            if ($rowIndex === 0) {
                $updatedHeader = $updatedRow;
                $codeIndex = array_search('code', $updatedHeader);
                $columnIndex = array_search($column, $updatedHeader);

                // $codeIdIndex = array_search('code_id', $updatedHeader);
            } else {
                if (is_null($columnIndex)) {
                    return;
                }

                $inventory = $inventoryRepository->findOneBy(['code' => $updatedRow[$codeIndex]]);

                if ($inventory) {
                    if ($entityColumn !== "price") {
                        $attachments = $inventory->getAttachments();
                    } else {
                        $attachments = $inventory->getPrice();
                    }

                    switch ($entityColumn) {
                        case 'image' : {
                            $attachments->setImage($updatedRow[$columnIndex]);
                            break;
                        }

                        case 'model' : {
                            $attachments->setModel($updatedRow[$columnIndex]);
                            break;
                        }
                        case 'price' : {

                        }
                    }

                    $entities[] = $attachments;
                }
            }
        }

        return $entities;
    }
}