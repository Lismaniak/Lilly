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
    public function listDummy(int $limit = 3): array
    {
        $names = [
            'Ada Lovelace',
            'Grace Hopper',
            'Alan Turing',
            'Katherine Johnson',
            'Edsger Dijkstra',
            'Linus Torvalds',
            'Leslie Lamport',
        ];

        $limit = max(1, min($limit, count($names)));

        $users = [];
        for ($i = 0; $i < $limit; $i++) {
            $user = new Users();
            $user->id = $i + 1;
            $user->name = $names[$i];
            $users[] = $user;
        }

        return $users;
    }
}
