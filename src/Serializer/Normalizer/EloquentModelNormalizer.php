<?php

/*
 * This file is part of the EloquentSerializer package.
 * 
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fidry\EloquentSerializer\Serializer\Normalizer;

use Fidry\EloquentSerializer\PropertyAccess\EloquentModelPropertyAccessor;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Théo FIDRY <theo.fidry@gmail.com>
 */
class EloquentModelNormalizer extends ObjectNormalizer
{
    /**
     * @var NormalizerInterface
     */
    protected $serializer;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    /**
     * @var \ReflectionMethod
     */
    private $arrayablePropertiesRefl;

    /**
     * @inheritdoc
     */
    public function __construct(
        ClassMetadataFactoryInterface $classMetadataFactory = null,
        NameConverterInterface $nameConverter = null,
        PropertyAccessorInterface $propertyAccessor = null,
        PropertyTypeExtractorInterface $propertyTypeExtractor = null
    ) {
        if (null === $propertyAccessor) {
            $propertyAccessor = new EloquentModelPropertyAccessor(PropertyAccess::createPropertyAccessor());
        }
        
        parent::__construct($classMetadataFactory, $nameConverter, $propertyAccessor, $propertyTypeExtractor);

        $this->arrayablePropertiesRefl = (new \ReflectionClass(EloquentModel::class))->getMethod('getArrayableItems');
        $this->arrayablePropertiesRefl->setAccessible(true);
    }

    /**
     * @inheritdoc
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (false === $serializer instanceof NormalizerInterface) {
            throw new \LogicException('Expected the serializer passed to be normalizer.');
        }

        parent::setSerializer($serializer);
    }


    /**
     * {@inheritdoc}
     *
     * @param Model $object
     */
    protected function extractAttributes($object, $format = null, array $context = array())
    {
        $attributes = $object->getAttributes();
        if (count($object->getVisible()) > 0) {
            $attributes = array_merge($attributes, array_flip($object->getVisible()));
        }

        /* @var EloquentModel[] $relations */
        $relations = $object->getRelations();
        foreach ($relations as $relationName => $relation) {
            if (!is_null($relation)) {
                unset($attributes[$relation->getForeignKey()]);
                $attributes[$relationName] = true;
            }
        }

        $attributes = array_diff_key($attributes, array_flip($object->getHidden()));

        return array_keys($attributes);
    }

    /**
     * @inheritdoc
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && $data instanceof EloquentModel;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        /** @var EloquentModel $object */
        $object = new $class();
        $objectRefl = new \ReflectionClass($class);

        $dates = array_intersect_key($data, array_flip($object->getDates()));
        foreach ($dates as $date => $value) {
            if ($object->hasSetMutator($date)) {
                continue;
            }
            if (is_null($value)) {
                continue;
            }

            $data[$date] = $this->serializer->denormalize($value, \DateTimeInterface::class, $format);
        }

        foreach ($data as $property => $value) {
            if (! (
                $objectRefl->hasMethod($method = str_replace('_', '', $property))
                || $objectRefl->hasMethod($method = $property)
            )) {
                $object->setAttribute($property, $value);

                continue;
            }

            $method = $objectRefl->getMethod($method)->getName();
            /** @var EloquentRelation $relation */
            $relation = $object->$method();
            if (is_array($value)) {
                $value = $this->denormalize($value, get_class($relation->getRelated()), $format, $context);
                $object->setRelation($method, $value);

                continue;
            }

            //$object->setAttribute($relation->getForeignKey(), $value);
        }

        return $object;
    }

    /**
     * @inheritdoc
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_subclass_of($type, EloquentModel::class, true);
    }

    public function __clone()
    {
        throw new \DomainException('You should not clone a service.');
    }
}
