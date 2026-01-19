<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;
use Domains\Users\Database\Tables\UserMailsTable;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->create(UserMailsTable::name(), function (Blueprint $t): void {
        UserMailsTable::define($t);
    });
};
