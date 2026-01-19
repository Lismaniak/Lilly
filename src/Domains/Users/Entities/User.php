<?php
declare(strict_types=1);

namespace Domains\Users\Entities;

use Lilly\Database\Orm\Attributes\Column;
use Lilly\Database\Orm\Attributes\Table;

#[Table('users')]
final class User
{
    #[Column('id', primary: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column('email')]
    public string $email = '';

    #[Column('name')]
    public string $name = '';
}
