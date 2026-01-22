<?php
declare(strict_types=1);

namespace Domains\Users\Entities;

use Lilly\Database\Orm\Attributes\Table;
use Lilly\Database\Orm\Attributes\Column;

#[Table('user_mails')]
final class UserMails
{
    #[Column('id', primary: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column('testmail')]
    public string $testmail = '';

    #[Column('testmail2')]
    public string $testmail2 = '';

    #[Column('testmail3', nullable: true)]
    public ?string $testmail3 = null;

    #[Column('user_id')]
    public int $userId = 0;

    #[Column('created_at')]
    public string $createdAt = '';

    #[Column('updated_at', nullable: true)]
    public ?string $updatedAt = null;
}
