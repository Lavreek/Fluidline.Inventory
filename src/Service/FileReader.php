<?php

namespace App\Service;

use App\Entity\InventoryPricehouse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;

class FileReader
{
    private array $products = [];

    private array $parameters = [];

    private array $naming = [];

    private string $readDirectory;

    private UploadedFile $file;

    public function setReadDirectory($readDirectory) : void
    {
        $this->readDirectory = $readDirectory;
    }

    public function setFile($file) : void
    {
        $this->file = $file;
    }

    public function getFile() : UploadedFile
    {
        return $this->file;
    }

    public function getReadDirectory() : string
    {
        return $this->readDirectory;
    }

    public function saveFile() : null|JsonResponse
    {
        $file = $this->file;

        try {
            file_put_contents(
                $this->getReadDirectory() . $file->getClientOriginalName(),
                $file->getContent()
            );

            return null;
        } catch (FileException $e) {
            return new JsonResponse(['error' => "Ошибка при загрузке файла.", 'exception' => $e->getMessage()]);
        }
    }

    private function getParameters($values) : void
    {
        $products = &$this->products;
        $parameters = &$this->parameters;
        $naming = &$this->naming;

        if (current($values)) {
            $key = key($values);
            $current = current($values);

            if (!empty($products)) {
                $productsInterim = [];

                foreach ($products as $product) {
                    for ($i = 0; $i < count($current); $i++) {
                        $productsInterim[] = $product;

                        if ($current[$i] !== '-') {
                            $productsInterim[count($productsInterim) - 1]['code'] =
                                $productsInterim[count($productsInterim) - 1]['code'] . '-' . $current[$i];
                        }

                        if (isset($parameters[$key])) {
                            $description = [];

                            if (isset($naming[$key])) {
                                foreach ($naming[$key] as $itemKey => $item) {
                                    $description[$itemKey] = $naming[$key][$itemKey][$i];
                                }
                            }

                            foreach ($parameters[$key] as $groupKey => $groupValue) {
                                if (!isset($parameters[$key][$groupKey][$i])) {
                                    $group = [
                                        'name' => $groupKey,
                                        'value' => "",
                                    ];
                                } else {
                                    $group = [
                                        'name' => $groupKey,
                                        'value' => trim($parameters[$key][$groupKey][$i], "\""),
                                    ];

                                    if (isset($description[$groupKey])) {
                                        $group['description'] = $description[$groupKey];
                                    }
                                }

                                $productsInterim[count($productsInterim) - 1]['parameters'][] = $group;
                            }
                        }
                    }
                }

                $products = $productsInterim;
            } else {
                for ($i = 0; $i < count($current); $i++) {
                    $products[$i]['code'] = $current[$i];
                    $products[$i]['parameters'] = [];

                    if (isset($parameters[$key])) {
                        $description = [];

                        if (isset($naming[$key])) {
                            foreach ($naming[$key] as $itemKey => $item) {
                                $description[$itemKey] = $naming[$key][$itemKey][$i];
                            }
                        }

                        foreach ($parameters[$key] as $groupKey => $groupValue) {
                            $group = [
                                'name' => $groupKey,
                                'value' => trim($parameters[$key][$groupKey][$i], "\""),
                            ];

                            if (isset($description[$groupKey])) {
                                $group['description'] = $description[$groupKey];
                            }

                            $products[$i]['parameters'][] = $group;
                        }
                    }
                }
            }

            next($values);
            $this->getParameters($values);
        }
    }

    private function getCSVValues($filepath) : array
    {
        $row = 0;

        $header = $position = $values = [];

        while ($data = fgetcsv($filepath, separator: ';')) {
            if (!preg_match('#\##', $data[0]) and $row === 0) {
                throw new \Exception("\n\n\tFirst column must be empty with heading \"#\"\n\n");
            }

            unset($data[0]);

            if ($row === 0) {
                $header = $data;

                foreach ($data as $columnKey => $columnData) {
                    $columnKey = trim($columnKey, "\"");
                    $columnData = trim($columnData, "\"");

                    if (preg_match('#Параметр:(.*)#u', $columnData, $match)) {
                        [$parameter, $name] = explode(':', $match[1]);
                        $this->parameters[$parameter][$name] = [];
                        $position['parameters'][] = $columnKey;

                    } elseif (preg_match('#Условное обозначение:(.*)#u', $columnData, $match)) {
                        $position['naming'][] = $columnKey;

                    } else {
                        $values += [$columnData => []];
                        $position['values'][] = $columnKey;
                    }
                }
            } else {
                foreach ($data as $columnKey => $columnData) {
                    if (!empty($columnData)) {
                        $columnKey = trim($columnKey, "\"");
                        $columnData = trim($columnData, "\"");

                        if (in_array($columnKey, $position['values'])) {
                            $values[$header[$columnKey]][] = $columnData;

                        } elseif (in_array($columnKey, $position['parameters'])) {
                            preg_match('#Параметр:(.*)#u', $header[$columnKey], $match);
                            [$parameter, $name] = explode(':', $match[1], 2);
                            $this->parameters[$parameter][$name][] = $columnData;

                        } elseif (in_array($columnKey, $position['naming'])) {
                            preg_match('#Условное обозначение:(.*)#u', $header[$columnKey], $match);
                            [$columnName, $columnTarget] = explode(":", $match[1], 2);
                            $this->naming[$columnName][$columnTarget][] = $columnData;
                        }
                    }
                }
            }

            $row++;
        }

        return $values;
    }

    public function executeCreate() : array
    {
        $directory = $this->getReadDirectory();

        $file = fopen($directory . $this->getFile()->getClientOriginalName(), 'r');

        $this->getParameters(
            $this->getCSVValues($file)
        );

        return $this->products;
    }

    private function replaceNBSP($content) : string
    {
        return str_replace(['﻿'], '', $content);
    }

    public function getCSVPrices($content) : array
    {
        $content = $this->replaceNBSP($content);

        $lines = explode("\n", $content);

        $entities = [];

        foreach ($lines as $line) {
            $data = explode(';', $line);

            if (!empty($data)) {
                $parameters = [];

                if (isset($data[0])) {
                    $parameters['code'] = $data[0];
                } else {
                    continue;
                }

                $parameters['count'] = 0;
                $parameters['price'] = 0;
                $parameters['currency'] = '$';

                if (isset($data[1])) {
                    if (!empty($data[1])) {
                        $parameters['count'] = $data[1];
                    }
                }

                if (isset($data[2])) {
                    if (!empty($data[2])) {
                        $parameters['price'] = $data[2];
                    }
                }

                if (isset($data[3])) {
                    if (!empty($data[3])) {
                        $parameters['currency'] = $this->getCurrency($data[3]);
                    }
                }

                $entities[] = $parameters;
            }
        }

        return $entities;
    }

    private function getCurrency($tag) : string
    {
        switch ($tag) {
            case 'RUB' : {
                return "₽";
            }

            case 'EUR' : {
                return "€";
            }

            case 'GBP' : {
                return "£";
            }

            default : {
                return "$";
            }
        }
    }
}