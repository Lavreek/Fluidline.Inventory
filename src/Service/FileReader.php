<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;

class FileReader
{
    private array $products = [];

    private array $parameters = [];

    private array $naming = [];

    private array $conditions = [];

    private array $special = [];

    private string $readDirectory;

    private string $fileDelimiter;

    private UploadedFile|string $file;

    public function setFileDelimiter($delimiter) : void
    {
        $this->fileDelimiter = $delimiter;
    }

    public function getFileDelimiter() : string
    {
        return $this->fileDelimiter;
    }

    public function setReadDirectory($readDirectory) : void
    {
        $this->readDirectory = $readDirectory;
    }

    public function setFile($file) : void
    {
        $this->file = $file;
    }

    public function getFile() : UploadedFile|string
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

    public function getCondition($conditionType, $parameterValue, $neededValue)
    {
        switch ($conditionType) {
            case '==' : {
                if ($parameterValue == $neededValue) {
                    return true;
                }
                break;
            }
        }

        return false;
    }

    public function checkCondition($productParameters, $conditionSet) : ?bool
    {
        unset($conditionSet['result']);

        foreach ($conditionSet as $condition) {
            preg_match('#(.*)(==|!=|>=|<=)(.*)#u', $condition, $match);

            $check = false;

            foreach ($productParameters as $parameter) {
                if ($parameter['name'] === $match[1]) {
                    $check = $this->getCondition($match[2], $parameter['value'], $match[3]);
                }
            }

            if ($check) {
                return true;
            }
        }

        return null;
    }

    private function setDescription($key, $index)
    {
        $naming = $this->naming;

        if (isset($naming[$key])) {
            foreach ($naming[$key] as $itemKey => $item) {
                if (isset($naming[$key][$itemKey][$index])) {
                    $description = $naming[$key][$itemKey][$index];

                    if ($description === "-") {
                        return null;
                    }

                    return $naming[$key][$itemKey][$index];
                }
            }
        }

        return null;
    }

    private function getParameters($values) : void
    {
        $products = &$this->products;
        $parameters = &$this->parameters;

        if (current($values)) {
            $key = key($values);
            $current = current($values);

            if (!empty($products)) {
                $productsInterim = [];

                foreach ($products as $product) {
                    for ($i = 0; $i < count($current); $i++) {
                        $productsInterim[] = $product;
                        $position = count($productsInterim);

                        if ($current[$i] !== '-') {
                            $productsInterim[$position - 1]['code'] =
                                $productsInterim[$position - 1]['code'] .'-'. $current[$i];
                        }

                        if (isset($parameters[$key])) {
                            $description = $this->setDescription($key, $i);

                            foreach ($parameters[$key] as $groupKey => $groupValue) {
                                if (isset($parameters[$key][$groupKey][$i])) {
                                    $group = [
                                        'name' => $groupKey,
                                        'value' => trim($parameters[$key][$groupKey][$i], "\""),
                                    ];

                                    if (isset($description[$groupKey])) {
                                        $group['description'] = $description[$groupKey];
                                    }

                                    $productsInterim[$position - 1]['parameters'][] = $group;
                                }
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
                        $description = $this->setDescription($key, $i);

                        foreach ($parameters[$key] as $groupKey => $groupValue) {
                            $group = [
                                'name' => $groupKey,
                                'value' => trim($parameters[$key][$groupKey][$i], "\""),
                            ];

                            if (!is_null($description)) {
                                $group['description'] = $description;
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

    private function getCSVValues($file) : array
    {
        $row = 0;

        $header = $position = $values = [];

        $delimiter = false;
        $tries = 0;
        while (!$delimiter) {
            $prev = stream_get_contents($file,1);

            if ($prev == '#') {
                $delimiter = stream_get_contents($file,1);
                $this->setFileDelimiter($delimiter);
            }

            if ($tries > 10) {
                return [];
            }

            $tries++;
        }

        rewind($file);

        while ($data = fgetcsv($file, separator: $this->getFileDelimiter())) {
            if (!preg_match('#\##', $data[0]) and $row === 0) {
                throw new \Exception("\nFirst column must be empty with heading \"#\"\n");
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

                    } elseif (preg_match('#Условие:(.*)#u', $columnData, $match)) {
                        $position['conditions'][] = $columnKey;
                        $this->conditions[$match[1]] = [];

                    } elseif (preg_match('#Особый параметр:(.*)#u', $columnData, $match)) {
                        $position['special'][] = $columnKey;
                        $this->special[$match[1]] = [];

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

                        }  elseif (in_array($columnKey, $position['conditions'])) {
                            preg_match('#ЕСЛИ\((.+)\)=\((.+)\)#u', $columnData, $match);

                            $exp = explode(":", $header[$columnKey], 2);
                            $conditionValue = $exp[1];

                            if (isset($match[0])) {
                                $conditionsExplode = explode('&', $match[1]);

                                foreach ($conditionsExplode as $exp) {
                                    $this->conditions[$conditionValue][] = $exp;
                                }

                                $this->conditions[$conditionValue]['result'] = $match[2];
                            }
                        } elseif (in_array($columnKey, $position['special'])) {
                            preg_match('#Особый параметр:(.*)#u', $header[$columnKey], $match);
                            $this->special[$match[1]][] = $columnData;
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

        $filename = $this->getFile();

        if (gettype($filename) === 'string') {
            $file = fopen($directory . $filename, 'r');

        } else {
            $file = fopen($directory . $filename->getClientOriginalName(), 'r');
        }

        $this->getParameters(
            $this->getCSVValues($file)
        );

        // TODO: SPECIAL MODIFICATIONS "$this->special"

        foreach ($this->products as $productsIndex => $productsElement) {
            foreach ($this->conditions as $conditionKey => $conditionSet) {
                $condition = $this->checkCondition($productsElement['parameters'], $conditionSet);

                if ($condition) {
                    $this->products[$productsIndex]['parameters'][] = [
                        'name' => $conditionKey,
                        'value' => $conditionSet['result'],
                    ];
                }
            }
        }

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