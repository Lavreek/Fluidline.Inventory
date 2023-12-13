<?php

namespace App\Controller\Inventory\Helpers;

use App\Service\Serializer;

class GetHelper
{
    static function prepareRequest($inventory, &$filter): array
    {
        $serializerArray = $filterOrder = $filterConstructed = [];

        foreach ($inventory as $item) {
            $serialize = Serializer::serializeElement($item);

            $object = json_decode($serialize, true);

            foreach ($object['parameters'] as $parameterIndex => $parameter) {
                $parameter['name'] = strip_tags($parameter['name'], ['a']);

                if (! in_array($parameter['name'], $filterOrder)) {
                    $filterOrder[] = $parameter['name'];
                }

                $object['parameters'][$parameterIndex]['value'] = strip_tags($object['parameters'][$parameterIndex]['value'], ['a']);

                unset($object['parameters'][$parameterIndex]['id'], $object['parameters'][$parameterIndex]['code']);
            }

            unset($object['price']['id'], $object['price']['code'], $object['created']);

            $serializerArray[] = $object;
        }

        foreach ($filterOrder as $filterPosition) {
            foreach ($filter as $item) {
                if ($item['name'] == $filterPosition) {
                    $filterConstructed[$item['name']]['values'][] = $item['value'];

                    if (! is_null($item['description'])) {
                        $filterConstructed[$item['name']]['descriptions'][] = $item['description'];
                    } else {
                        $filterConstructed[$item['name']]['descriptions'][] = "";
                    }
                }
            }
        }

        $filter = $filterConstructed;

        return $serializerArray;
    }
}