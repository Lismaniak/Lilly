<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Repository;

use Lilly\Database\Orm\EntityHydrator;
use Lilly\Database\Orm\EntityPersister;
use Lilly\Database\Orm\Orm;

abstract class RepositoryBase
{
    protected readonly EntityHydrator $hydrator;
    protected readonly EntityPersister $persister;

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        protected readonly Orm $orm,
        protected readonly string $entityClass
    ) {
        $this->hydrator = new EntityHydrator($orm->meta);
        $this->persister = new EntityPersister($orm->meta);
    }
}
