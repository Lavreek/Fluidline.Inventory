<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;

class FileReader extends FileHelper
{
    private int $maxProductsCount;

    private array $products = [];

    private array $parameters = [];

    private array $naming = [];

    private array $conditions = [];

    private array $special = [];

    private string $readDirectory;


    private UploadedFile|string $file;

    public function setMaxProductCount($count) : self
    {
        $this->maxProductsCount = $count;

        return $this;
    }

    public function setReadDirectory($readDirectory): void
    {
        $this->readDirectory = $readDirectory;
    }

    public function setFile($file): void
    {
        $this->file = $file;
    }

    public function getFile(): UploadedFile|string
    {
        return $this->file;
    }

    public function getReadDirectory(): string
    {
        return $this->readDirectory;
    }

    public function saveFile(): null|JsonResponse
    {
        $file = $this->file;

        try {
            file_put_contents($this->getReadDirectory() . $file->getClientOriginalName(), $file->getContent());

            return null;
        } catch (FileException $e) {
            return new JsonResponse([
                'error' => "Ошибка при загрузке файла.",
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getCondition($conditionType, $parameterValue, $neededValue)
    {
        switch ($conditionType) {
            case '==':
                {
                    if ($parameterValue == $neededValue) {
                        return true;
                    }
                    break;
                }
        }

        return false;
    }

    public function checkCondition($productParameters, $conditionSet): ?bool
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

    private function getProducts($codes)
    {
        $CSVProducts = [];

        while ($codes) {
            $codesProducts = array_shift($codes);
            $newProducts = [];

            foreach ($codesProducts['values'] as $codesProducts_key => $codesProducts_value) {

                if (empty($codesProducts_value)) { continue; }
                if ($codesProducts_value == "-") { $codesProducts_value = ""; }

                /** -- -- --
                 * Массив параметров
                 */
                $parameters = [];

                /** -- -- --
                 * Массив условных обозначений
                 */
                $naming = [];

                if (isset($this->parameters[$codesProducts['name']])) {
                    foreach ($this->parameters[$codesProducts['name']] as $parameters_key => $parameters_value) {
                        /** -- -- --
                         * Добавление условных обозначений
                         */
                        if (
                            isset($this->naming[$codesProducts['name']]) and
                            isset($this->naming[$codesProducts['name']][$parameters_key])
                        ) {
                            if (!empty($this->naming[$codesProducts['name']][$parameters_key])) {
                                $naming[$parameters_key] = $this->naming[$codesProducts['name']][$parameters_key][$codesProducts_key];
                            }
                        }
                        /** -- -- -- */

                        $parameters[$parameters_key] = $parameters_value[$codesProducts_key];
                    }
                }
                /** -- -- -- */

                $newProducts['codes'][] = $codesProducts_value;
                $newProducts['parameters'][] = $parameters;
                $newProducts['naming'][] = $naming;
            }

            if (!empty($CSVProducts)) {
                $middle = [];

                $delimiter = "-";

                for ($i = 0; $i < count($CSVProducts['codes']); $i++) {
                    for ($j = 0; $j < count($newProducts['codes']); $j++) {
                        $middle['codes'][] = trim(
                            $CSVProducts['codes'][$i] . $delimiter . $newProducts['codes'][$j],
                            "- \t\n\r\v"
                        );

                        $middle['parameters'][] = $CSVProducts['parameters'][$i] + $newProducts['parameters'][$j];
                        $middle['naming'][] = $CSVProducts['naming'][$i] + $newProducts['naming'][$j];
                    }
                }

                $newProducts = $middle;
            }

            $CSVProducts = $newProducts;
        }

        if (count($CSVProducts) > $this->maxProductsCount) {
            echo "Количество продуктов превышает допустимое: {$this->maxProductsCount}\n";
            return count($CSVProducts);
        }

        return $CSVProducts;
    }

    private function getCSVValues($file): array
    {
        $row = 0;

        $delimiter = $this->getFileDelimiter($file);

        if (
            (is_string($delimiter) and empty($delimiter)) or
            (is_bool($delimiter) and !$delimiter)
        ) {
            return [];
        }

        $values = $parameters = $naming = [];

        while ($data = fgetcsv($file, separator: $delimiter)) {
            //if (!preg_match('#\##', $data[0]) and $row === 0) {
            //    throw new \Exception("\nFirst column must be empty with heading \"#\"\n");
            //}
            unset($data[0]);
            $data = array_values($data);

            if ($row == 0) {
                foreach ($data as $columnKey => $columnData) {
                    $columnKey = trim($columnKey, "\"");
                    $columnData = trim($columnData, "\"");

                    if (preg_match('#Параметр:(.*)#u', $columnData, $match)) {
                        [$parameter, $name] = explode(':', $match[1], 2);

                        $this->parameters[$parameter][$name] = [];
                        $parameters[$columnKey] = ['parameter' => $parameter, 'name' => $name];

                    } elseif (preg_match('#Условное обозначение:(.*)#u', $columnData, $match)) {
                        [$parameter, $name] = explode(':', $match[1], 2);

                        $this->naming[$parameter][$name] = [];
                        $naming[$columnKey] = ['parameter' => $parameter, 'name' => $name];

                    } elseif (preg_match('#Условие:(.*)#u', $columnData, $match)) {
                        $this->conditions[$match[1]] = [];

                    } elseif (preg_match('#Особый параметр:(.*)#u', $columnData, $match)) {
                        $this->special[$match[1]] = [];

                    } else {
                        $values[$columnKey] = ['name' => $columnData, 'values' => []];
                    }
                }

            } else {
                foreach ($data as $columnKey => $columnValue) {
                    $columnKey = trim($columnKey, "\"");
                    $columnValue = trim($columnValue, "\"");

                    if (isset($values[$columnKey])) {
                        $values[$columnKey]['values'][] = $columnValue;
                    }

                    if (isset($parameters[$columnKey])) {
                        $parameter = $parameters[$columnKey]['parameter'];
                        $name = $parameters[$columnKey]['name'];

                        $this->parameters[$parameter][$name][] = $columnValue;
                    }

                    if (isset($naming[$columnKey])) {
                        $parameter = $naming[$columnKey]['parameter'];
                        $name = $naming[$columnKey]['name'];

                        $this->naming[$parameter][$name][] = $columnValue;
                    }

                }
            }

            $row++;
        }

        return $values;
    }

    public function executeCreate(): array|int
    {
        $directory = $this->getReadDirectory();

        $filename = $this->getFile();

        if (gettype($filename) === 'string') {
            $file = fopen($directory . $filename, 'r');
        } else {
            $file = fopen($directory . $filename->getClientOriginalName(), 'r');
        }

        $products = $this->getProducts(
            $this->getCSVValues($file)
        );

        // TODO: SPECIAL MODIFICATIONS "$this->special"

//        foreach ($product as $productsIndex => $productsElement) {
//            foreach ($this->conditions as $conditionKey => $conditionSet) {
//                $condition = $this->checkCondition($productsElement['parameters'], $conditionSet);
//
//                if ($condition) {
//                    $this->products[$productsIndex]['parameters'][] = [
//                        'name' => $conditionKey,
//                        'value' => $conditionSet['result']
//                    ];
//                }
//            }
//        }

        return $products;
    }

    private function replaceNBSP($content): string
    {
        return str_replace([
            '﻿'
        ], '', $content);
    }

    public function getCSVPrices($content): array
    {
        $content = $this->replaceNBSP($content);

        $lines = explode("\n", $content);

        $entities = [];

        foreach ($lines as $line) {
            $data = explode(';', $line);

            if (! empty($data)) {
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
                    if (! empty($data[1])) {
                        $parameters['count'] = $data[1];
                    }
                }

                if (isset($data[2])) {
                    if (! empty($data[2])) {
                        $parameters['price'] = $data[2];
                    }
                }

                if (isset($data[3])) {
                    if (! empty($data[3])) {
                        $parameters['currency'] = $this->getCurrency($data[3]);
                    }
                }

                $entities[] = $parameters;
            }
        }

        return $entities;
    }

    private function getCurrency($tag): string
    {
        switch ($tag) {
            case 'RUB':
                {
                    return "₽";
                }

            case 'EUR':
                {
                    return "€";
                }

            case 'GBP':
                {
                    return "£";
                }

            default:
                {
                    return "$";
                }
        }
    }
}
