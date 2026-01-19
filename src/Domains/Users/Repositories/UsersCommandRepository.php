<?php
declare(strict_types=1);

namespace Domains\Users\Repositories;

use Domains\Users\Entities\User;
use Lilly\Database\Orm\Orm;
use Lilly\Database\Orm\Repository\CommandRepository;

final class UsersCommandRepository extends CommandRepository
{
    public function __construct(Orm $orm)
    {
        parent::__construct($orm, User::class);
    }

    public function saveUser(User $user): void
    {
        $this->save($user);
    }

    public function deleteUser(User $user): void
    {
        $this->delete($user);
    }
}
