<?php

namespace App\Service;

class FileReader
{
    private array $products = [];

    private array $parameters = [];

    private array $naming = [];

    private function getParameters($values) : void
    {
        if (current($values)) {
            $key = key($values);
            $current = current($values);

            if (!empty($this->products)) {
                $productsInterim = [];
                foreach ($this->products as $product) {
                    for ($i = 0; $i < count($current); $i++) {
                        $productsInterim[] = $product;

                        if ($current[$i] !== '-') {
                            $productsInterim[count($productsInterim) - 1]['code'] = $productsInterim[count($productsInterim) - 1]['code'] . '-' . $current[$i];
                        }

                        if (isset($this->parameters[$key])) {
                            $description = [];

                            if (isset($this->naming[$key])) {
                                $description = ['description' => $this->naming[$key][$i]];
                            }

                            $productsInterim[count($productsInterim) - 1]['parameters'][] = [
                                    'name' => $this->parameters[$key]['name'],
                                    'value' => $this->parameters[$key]['values'][$i]
                                ] + $description;
                        }
                    }
                }

                $this->products = $productsInterim;
            } else {
                for ($i = 0; $i < count($current); $i++) {
                    $this->products[]['code'] = $current[$i];

                    $this->products[$i]['parameters'] = [];

                    if (isset($this->parameters[$key])) {
                        $description = [];

                        if (isset($this->naming[$key])) {
                            $description = ['description' => $this->naming[$key][$i]];
                        }

                        $this->products[$i]['parameters'][] = [
                            'name' => $this->parameters[$key]['name'],
                            'value' => $this->parameters[$key]['values'][$i]
                        ] + $description;
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

        while ($data = fgetcsv($file, separator: ';')) {
            if (!preg_match('#\##', $data[0]) and $row === 0) {
                throw new \Exception("\n\n\tFirst column must be empty with heading \"#\"\n\n");
            }

            unset($data[0]);

            if ($row === 0) {
                $header = $data;

                foreach ($data as $columnKey => $columnData) {
                    if (preg_match('#Параметр:(.*)#u', $columnData, $match)) {
                        [$parameter, $name] = explode(':', $match[1]);
                        $this->parameters += [$parameter => ['name' => $name, 'values' => []]];
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
                        if (in_array($columnKey, $position['values'])) {
                            $values[$header[$columnKey]][] = $columnData;

                        } elseif (in_array($columnKey, $position['parameters'])) {
                            preg_match('#Параметр:(.*)#u', $header[$columnKey], $match);
                            [$parameter, $name] = explode(':', $match[1]);
                            $this->parameters[$parameter]['values'][] = $columnData;

                        } elseif (in_array($columnKey, $position['naming'])) {
                            preg_match('#Условное обозначение:(.*)#u', $header[$columnKey], $match);
                            $this->naming[$match[1]][] = $columnData;
                        }
                    }
                }
            }

            $row++;
        }

        return $values;
    }

    public function executeCreate($filepath) : array
    {
        $file = fopen($filepath, 'r');

        $this->getParameters(
            $this->getCSVValues($file)
        );

        return $this->products;
    }
}