<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->table('user_mails', function (Blueprint $t): void {
        $t->unsignedBigInteger('user_id');

        $t->foreignKey('user_id', 'id', 'users', 'cascade');
    });
};
