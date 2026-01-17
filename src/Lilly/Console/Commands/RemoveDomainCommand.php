<?php

declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveDomainCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    )
    {
        parent::__construct('shape:domain:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove an existing Domain and all its files')
            ->addArgument('name', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName(
            (string)$input->getArgument('name')
        );

        $domainRoot = $this->projectRoot . '/src/Domain/' . $domain;

        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain}");
            return Command::FAILURE;
        }

        if (!str_starts_with(realpath($domainRoot), realpath($this->projectRoot . '/src/Domain'))) {
            $output->writeln("<error>Refusing to delete outside src/Domain</error>");
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');

        $question = new ConfirmationQuestion(
            "This will permanently delete the domain '{$domain}'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        $this->deleteDirectory($domainRoot);

        $output->writeln("<info>Removed domain:</info> {$domain}");
        return Command::SUCCESS;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? $name;

        return ucfirst($name);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;

            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }
}
