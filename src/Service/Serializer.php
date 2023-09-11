<?php

namespace App\Service;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class Serializer
{
    static function serializeElement($item) : string
    {
        $encoders = [new JsonEncoder()];

        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER
            => function (object $object, string $format, array $context) : string
            {
                return $object->getCode();
            },
        ];

        $normalizers = [new ObjectNormalizer(defaultContext: $defaultContext)];

        $serializer = new \Symfony\Component\Serializer\Serializer($normalizers, $encoders);

        return $serializer->serialize($item, 'json');
    }
}