<?php
declare(strict_types=1);

namespace Domains\Users\Repositories;

use Domains\Users\Entities\Users;
use Lilly\Database\Orm\Orm;
use Lilly\Database\Orm\Repository\QueryRepository;

final class UsersQueryRepository extends QueryRepository
{
    public function __construct(Orm $orm)
    {
        parent::__construct($orm, Users::class);
    }

    /**
     * @return list<Users>
     */
    public function listDummy(?int $limit = null): array
    {
        $names = [
            'AdaLovelace',
            'GraceHopper',
            'AlanTuring',
            'KatherineJohnson',
            'EdsgerDijkstra',
            'LinusTorvalds',
            'LeslieLamport',
        ];

        if ($limit !== null) {
            $limit = max(1, min($limit, count($names)));
            $names = array_slice($names, 0, $limit);
        }

        $users = [];
        $baseDate = new \DateTimeImmutable('2024-01-01 09:00:00');

        foreach ($names as $i => $name) {
            $user = new Users();
            $user->id = $i + 1;
            $user->name = $name;
            $user->createdAt = $baseDate->modify(sprintf('+%d days', $i))->format('Y-m-d H:i:s');
            $user->updatedAt = $baseDate->modify(sprintf('+%d days', $i + 1))->format('Y-m-d H:i:s');
            $users[] = $user;
        }

        return $users;
    }
}
