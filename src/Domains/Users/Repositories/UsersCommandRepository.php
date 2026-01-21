<?php
declare(strict_types=1);

namespace Domains\Users\Repositories;

use DateTimeImmutable;
use Domains\Users\Entities\Users;
use Lilly\Database\Orm\Orm;
use Lilly\Database\Orm\Repository\CommandRepository;

final class UsersCommandRepository extends CommandRepository
{
    public function __construct(Orm $orm)
    {
        parent::__construct($orm, Users::class);
    }

    public function createWithName(string $name): Users
    {
        $user = $this->newEntity($name);
        $this->save($user);

        return $user;
    }

    public function newEntity(string $name): Users
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $user = new Users();
        $user->name = $name;
        $user->createdAt = $now;
        $user->updatedAt = $now;

        return $user;
    }
}
