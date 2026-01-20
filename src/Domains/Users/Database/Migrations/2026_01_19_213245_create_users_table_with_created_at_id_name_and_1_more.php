<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->create('users', function (Blueprint $t): void {
        $t->timestamp('created_at');
        $t->id('id');
        $t->string('name', 255);
        $t->timestamp('updated_at')->nullable();
    });
};
