<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use function count;
use function array_key_exists;
use function is_array;
use function is_string;

final class UnitOfWorkGetEntityChangeSetDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
        private ObjectMetadataResolver $objectMetadataResolver;

        private DescriptorRegistry $descriptorRegistry;

        public function __construct(ObjectMetadataResolver $objectMetadataResolver, DescriptorRegistry $descriptorRegistry)
        {
                $this->objectMetadataResolver = $objectMetadataResolver;
                $this->descriptorRegistry = $descriptorRegistry;
        }

        public function getClass(): string
        {
                return UnitOfWork::class;
        }

        public function isMethodSupported(MethodReflection $methodReflection): bool
        {
                return $methodReflection->getName() === 'getEntityChangeSet';
        }

        public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
        {
                if (count($methodCall->getArgs()) === 0) {
                        return $methodReflection->getVariants()[0]->getReturnType();
                }

                $entityType = $scope->getType($methodCall->getArgs()[0]->value);
                $classNames = TypeUtils::getDirectClassNames($entityType);

                if ($classNames === []) {
                        return $methodReflection->getVariants()[0]->getReturnType();
                }

                $types = [];

                foreach ($classNames as $className) {
                        $metadata = $this->objectMetadataResolver->getClassMetadata($className);
                        if ($metadata === null) {
                                continue;
                        }

                        $types[] = $this->createChangeSetType($metadata);
                }

                if ($types === []) {
                        return $methodReflection->getVariants()[0]->getReturnType();
                }

                return TypeCombinator::union(...$types);
        }

        /**
         * @param ClassMetadata<object> $metadata
         */
        private function createChangeSetType(ClassMetadata $metadata): Type
        {
                $builder = ConstantArrayTypeBuilder::createEmpty();

                foreach ($metadata->fieldMappings as $mapping) {
                        if (!is_array($mapping)) {
                                continue;
                        }

                        $fieldName = $mapping['fieldName'] ?? null;
                        if (!is_string($fieldName)) {
                                continue;
                        }

                        $builder->setOffsetValueType(
                                new ConstantStringType($fieldName),
                                $this->createFieldChangeSetType($mapping),
                                true
                        );
                }

                foreach ($metadata->associationMappings as $mapping) {
                        if (!is_array($mapping)) {
                                continue;
                        }

                        $fieldName = $mapping['fieldName'] ?? null;
                        if (!is_string($fieldName)) {
                                continue;
                        }

                        $builder->setOffsetValueType(
                                new ConstantStringType($fieldName),
                                $this->createAssociationChangeSetType($mapping),
                                true
                        );
                }

                return $builder->getArray();
        }

        /**
         * @param array<mixed> $mapping
         */
        private function createFieldChangeSetType(array $mapping): Type
        {
                $typeName = $mapping['type'] ?? null;
                $propertyType = new MixedType();

                if (is_string($typeName)) {
                        try {
                                $propertyType = $this->descriptorRegistry->get($typeName)->getWritableToPropertyType();
                        } catch (DescriptorNotRegisteredException $e) {
                                $propertyType = new MixedType();
                        }
                }

                if (($mapping['nullable'] ?? false) === true) {
                        $propertyType = TypeCombinator::addNull($propertyType);
                }

                $propertyType = TypeCombinator::addNull($propertyType);

                return $this->createPairType($propertyType);
        }

        /**
         * @param array<mixed> $mapping
         */
        private function createAssociationChangeSetType(array $mapping): Type
        {
                $type = new MixedType();

                $mappingType = $mapping['type'] ?? null;

                if ($mappingType === ClassMetadata::ONE_TO_MANY || $mappingType === ClassMetadata::MANY_TO_MANY) {
                        return $this->createPairType(new MixedType());
                }

                if (is_string($mapping['targetEntity'] ?? null)) {
                        $type = new ObjectType($mapping['targetEntity']);
                }

                $nullable = true;
                if (array_key_exists('joinColumns', $mapping) && is_array($mapping['joinColumns'])) {
                        $nullable = false;
                        foreach ($mapping['joinColumns'] as $joinColumn) {
                                if (($joinColumn['nullable'] ?? false) === true) {
                                        $nullable = true;
                                        break;
                                }
                        }
                }

                if ($nullable) {
                        $type = TypeCombinator::addNull($type);
                }

                $type = TypeCombinator::addNull($type);

                return $this->createPairType($type);
        }

        private function createPairType(Type $valueType): Type
        {
                $builder = ConstantArrayTypeBuilder::createEmpty();
                $builder->setOffsetValueType(new ConstantIntegerType(0), $valueType);
                $builder->setOffsetValueType(new ConstantIntegerType(1), $valueType);

                return $builder->getArray();
        }
}
