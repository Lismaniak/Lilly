<?php

declare(strict_types=1);

namespace Lilly\Console;

use Symfony\Component\Console\Application;
use Lilly\Console\Commands\MakeDomainCommand;

final class ApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $app = new Application('Lilly', '0.1.0');

        $app->addCommand(new MakeDomainCommand(projectRoot: $projectRoot));

        return $app;
    }
}
