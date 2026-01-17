<?php

declare(strict_types=1);

namespace Lilly\Console;

use Symfony\Component\Console\Application;
use Lilly\Console\Commands\MakeDomainCommand;
use Lilly\Console\Commands\RemoveDomainCommand;

final class ApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $app = new Application('Lilly', '0.1.0');

        $app->addCommand(new MakeDomainCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveDomainCommand(projectRoot: $projectRoot));

        return $app;
    }
}
