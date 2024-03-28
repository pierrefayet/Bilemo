<?php

namespace App\Service;

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\Construction\UnserializeObjectConstructor;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Visitor\DeserializationVisitorInterface;

class ExistingObjectConstructor implements ObjectConstructorInterface
{
    private ObjectConstructorInterface $fallbackConstructor;

    public function __construct()
    {
        $this->fallbackConstructor = new UnserializeObjectConstructor();
    }

    /**
     * @param array< mixed > $type
     */
    public function construct(
        DeserializationVisitorInterface $visitor,
        ClassMetadata $metadata,
        mixed $data,
        array $type,
        DeserializationContext $context
    ): ?object {
        if ($context->hasAttribute('target') && 1 === $context->getDepth() && is_object($context->getAttribute('target'))) {
            return $context->getAttribute('target');
        }

        return $this->fallbackConstructor->construct($visitor, $metadata, $data, $type, $context);
    }
}
