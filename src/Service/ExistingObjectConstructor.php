<?php

namespace App\Service;

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Visitor\DeserializationVisitorInterface;

class ExistingObjectConstructor implements ObjectConstructorInterface
{
    private ObjectConstructorInterface $fallbackConstructor;

    /**
     * @param string $fallbackConstructorClassName Fallback object constructor
     */
    public function __construct(string $fallbackConstructorClassName)
    {
        $instance = new $fallbackConstructorClassName();
        if (!$instance instanceof ObjectConstructorInterface) {
            throw new \InvalidArgumentException(sprintf('The class %s must implement ObjectConstructorInterface.', $fallbackConstructorClassName));
        }
        $this->fallbackConstructor = $instance;
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
