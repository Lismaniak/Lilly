<?php
declare(strict_types=1);

use Lilly\Database\Schema\Schema;
use Lilly\Database\Schema\Blueprint;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->create('users_names', function (Blueprint $t): void {
        $t->id();
        $t->timestamps();
    });
};
