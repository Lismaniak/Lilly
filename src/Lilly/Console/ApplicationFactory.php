<?php

declare(strict_types=1);

namespace Lilly\Console;

use Lilly\Console\Commands\RemoveCrossComponentCommand;
use Lilly\Console\Commands\MakeCrossComponentCommand;
use Lilly\Console\Commands\RemoveDomainCommand;
use Lilly\Console\Commands\MakeDomainCommand;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $app = new Application('Lilly', '0.1.0');

        $app->addCommand(new MakeDomainCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveDomainCommand(projectRoot: $projectRoot));
        $app->addCommand(new MakeCrossComponentCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveCrossComponentCommand(projectRoot: $projectRoot));

        return $app;
    }
}
