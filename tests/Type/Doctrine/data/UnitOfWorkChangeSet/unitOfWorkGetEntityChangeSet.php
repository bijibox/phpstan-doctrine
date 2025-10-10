<?php declare(strict_types = 1);

namespace UnitOfWorkChangeSet;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use QueryResult\Entities\Many;
use QueryResult\Entities\Simple;
use function PHPStan\Testing\assertType;

class UnitOfWorkGetEntityChangeSet
{
        public function describeSimple(EntityManagerInterface $em, Simple $simple): void
        {
                $unitOfWork = $em->getUnitOfWork();
                $changeSet = $unitOfWork->getEntityChangeSet($simple);

                assertType(
                        'array{
                                intColumn?: array{0: int|null, 1: int|null},
                                floatColumn?: array{0: float|null, 1: float|null},
                                decimalColumn?: array{0: string|null, 1: string|null},
                                stringColumn?: array{0: string|null, 1: string|null},
                                stringNullColumn?: array{0: string|null, 1: string|null},
                                mixedColumn?: array{0: mixed|null, 1: mixed|null}
                        }',
                        $changeSet
                );

                if (array_key_exists('stringNullColumn', $changeSet)) {
                        assertType('array{0: string|null, 1: string|null}', $changeSet['stringNullColumn']);
                }
        }

        public function describeMany(EntityManagerInterface $em, Many $many): void
        {
                $unitOfWork = $em->getUnitOfWork();
                $changeSet = $unitOfWork->getEntityChangeSet($many);

                assertType(
                        'array{
                                intColumn?: array{0: int|null, 1: int|null},
                                stringColumn?: array{0: string|null, 1: string|null},
                                stringNullColumn?: array{0: string|null, 1: string|null},
                                datetimeColumn?: array{0: \DateTime|null, 1: \DateTime|null},
                                datetimeImmutableColumn?: array{0: \DateTimeImmutable|null, 1: \DateTimeImmutable|null},
                                one?: array{0: QueryResult\\Entities\\One|null, 1: QueryResult\\Entities\\One|null},
                                oneNull?: array{0: QueryResult\\Entities\\One|null, 1: QueryResult\\Entities\\One|null},
                                oneDefaultNullability?: array{0: QueryResult\\Entities\\One|null, 1: QueryResult\\Entities\\One|null},
                                compoundPk?: array{0: QueryResult\\Entities\\CompoundPk|null, 1: QueryResult\\Entities\\CompoundPk|null},
                                compoundPkAssoc?: array{0: QueryResult\\Entities\\CompoundPkAssoc|null, 1: QueryResult\\Entities\\CompoundPkAssoc|null},
                                simpleArrayColumn?: array{0: list<string>|null, 1: list<string>|null}
                        }',
                        $changeSet
                );

                if (array_key_exists('intColumn', $changeSet)) {
                        assertType('array{0: int|null, 1: int|null}', $changeSet['intColumn']);
                }

                if (array_key_exists('datetimeColumn', $changeSet)) {
                        assertType('array{0: \DateTime|null, 1: \DateTime|null}', $changeSet['datetimeColumn']);
                }

                if (array_key_exists('one', $changeSet)) {
                        assertType('array{0: QueryResult\\Entities\\One|null, 1: QueryResult\\Entities\\One|null}', $changeSet['one']);
                }

                assertType('array{0: list<string>|null, 1: list<string>|null}|null', $changeSet['simpleArrayColumn'] ?? null);

                if (array_key_exists('simpleArrayColumn', $changeSet)) {
                        assertType('array{0: list<string>|null, 1: list<string>|null}', $changeSet['simpleArrayColumn']);
                }
        }

        public function describeDynamic(UnitOfWork $unitOfWork, Simple|Many $entity): void
        {
                assertType('array', $unitOfWork->getEntityChangeSet($entity));
        }
}
