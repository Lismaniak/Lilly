<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->table('users', function (Blueprint $t): void {
        // DROP inferred: removed from define()
        $t->drop('test3');
    });
};
