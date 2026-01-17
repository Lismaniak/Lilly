<?php

declare(strict_types=1);

namespace Lilly\Console;

use Lilly\Console\Commands\MakeAppComponentCommand;
use Lilly\Console\Commands\MakeGateCommand;
use Lilly\Console\Commands\RemoveAppComponentCommand;
use Lilly\Console\Commands\RemoveCrossComponentCommand;
use Lilly\Console\Commands\MakeCrossComponentCommand;
use Lilly\Console\Commands\RemoveDomainCommand;
use Lilly\Console\Commands\MakeDomainCommand;
use Lilly\Console\Commands\RemoveGateCommand;
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
        $app->addCommand(new MakeGateCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveGateCommand(projectRoot: $projectRoot));
        $app->addCommand(new MakeAppComponentCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveAppComponentCommand(projectRoot: $projectRoot));

        return $app;
    }
}
