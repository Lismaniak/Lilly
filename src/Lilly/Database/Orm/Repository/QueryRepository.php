<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Repository;

abstract class QueryRepository extends RepositoryBase
{
    public function find(int|string $id): ?object
    {
        $m = $this->orm->meta->for($this->entityClass);

        $q = $this->orm->qb
            ->select($m->table)
            ->where($m->primaryColumn, '=', $id)
            ->limit(1)
            ->build();

        $row = $this->orm->fetchOne($q);
        if ($row === null) {
            return null;
        }

        return $this->hydrator->one($this->entityClass, $row);
    }

    /**
     * @param array<string, mixed> $criteria column => value
     */
    public function findOneBy(array $criteria): ?object
    {
        $m = $this->orm->meta->for($this->entityClass);

        $qb = $this->orm->qb->select($m->table);
        foreach ($criteria as $col => $val) {
            $qb->where($col, '=', $val);
        }

        $row = $this->orm->fetchOne($qb->limit(1)->build());
        return $row === null ? null : $this->hydrator->one($this->entityClass, $row);
    }

    /**
     * @param array<string, mixed> $criteria column => value
     * @return list<object>
     */
    public function findBy(array $criteria, int $limit = 50): array
    {
        $m = $this->orm->meta->for($this->entityClass);

        $qb = $this->orm->qb->select($m->table);
        foreach ($criteria as $col => $val) {
            $qb->where($col, '=', $val);
        }

        $rows = $this->orm->fetchAll($qb->limit($limit)->build());
        return $this->hydrator->many($this->entityClass, $rows);
    }
}
