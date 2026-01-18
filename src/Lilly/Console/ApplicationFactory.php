<?php

declare(strict_types=1);

namespace Lilly\Console;

use Lilly\Config\Config;
use Lilly\Console\Commands\DbTableMakeCommand;
use Lilly\Console\Commands\DbTableRemoveCommand;
use Lilly\Console\Commands\DbTableUpdateCommand;
use Lilly\Console\Commands\MakeAppComponentCommand;
use Lilly\Console\Commands\MakeAppGateCommand;
use Lilly\Console\Commands\MakeAppPolicyCommand;
use Lilly\Console\Commands\MakeGateCommand;
use Lilly\Console\Commands\RemoveAppComponentCommand;
use Lilly\Console\Commands\RemoveAppGateCommand;
use Lilly\Console\Commands\RemoveAppPolicyCommand;
use Lilly\Console\Commands\RemoveCrossComponentCommand;
use Lilly\Console\Commands\MakeCrossComponentCommand;
use Lilly\Console\Commands\RemoveDomainCommand;
use Lilly\Console\Commands\MakeDomainCommand;
use Lilly\Console\Commands\RemoveGateCommand;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public static function create(string $projectRoot, Config $config): Application
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
        $app->addCommand(new MakeAppPolicyCommand(projectRoot: $projectRoot));
        $app->addCommand(new MakeAppGateCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveAppPolicyCommand(projectRoot: $projectRoot));
        $app->addCommand(new RemoveAppGateCommand(projectRoot: $projectRoot));
        $app->addCommand(new DbTableMakeCommand(projectRoot: $projectRoot));
        $app->addCommand(new DbTableUpdateCommand(projectRoot: $projectRoot));
        $app->addCommand(new DbTableRemoveCommand(projectRoot: $projectRoot));

        return $app;
    }
}
