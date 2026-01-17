<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveAppGateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:gate:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove an App gate class from src/App/Policies/Gates')
            ->addArgument('name', InputArgument::REQUIRED, 'Gate class name, e.g. CanAccessAdmin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $gateClass = $this->normalizeClassName((string) $input->getArgument('name'));

        if ($gateClass === '') {
            $output->writeln('<error>Usage: shape:app:gate:remove <GateClass></error>');
            return Command::FAILURE;
        }

        $gatesDir = "{$this->projectRoot}/src/App/Policies/Gates";
        if (!is_dir($gatesDir)) {
            $output->writeln('<error>No app gates folder found:</error> src/App/Policies/Gates');
            return Command::FAILURE;
        }

        $gatePath = "{$gatesDir}/{$gateClass}.php";
        if (!is_file($gatePath)) {
            $output->writeln("<error>App gate does not exist:</error> App\\Policies\\Gates\\{$gateClass}");
            return Command::FAILURE;
        }

        $realBase = realpath($gatesDir);
        $realFile = realpath($gatePath);

        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            $output->writeln('<error>Refusing to delete outside src/App/Policies/Gates</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This will permanently delete app gate '{$gateClass}'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        unlink($gatePath);
        $output->writeln(' + file removed ' . $this->rel($gatePath));

        // If Gates folder is now empty, delete it too
        if ($this->isDirectoryEmpty($gatesDir)) {
            rmdir($gatesDir);
            $output->writeln(' + dir  removed ' . $this->rel($gatesDir));
        }

        // If Policies folder is now empty, delete it too
        $policiesDir = "{$this->projectRoot}/src/App/Policies";
        if (is_dir($policiesDir) && $this->isDirectoryEmpty($policiesDir)) {
            rmdir($policiesDir);
            $output->writeln(' + dir  removed ' . $this->rel($policiesDir));
        }

        // If App folder is now empty, delete it too
        $appBase = "{$this->projectRoot}/src/App";
        if (is_dir($appBase) && $this->isDirectoryEmpty($appBase)) {
            rmdir($appBase);
            $output->writeln(' + dir  removed ' . $this->rel($appBase));
        }

        $output->writeln("<info>Removed app gate:</info> App\\Policies\\Gates\\{$gateClass}");
        return Command::SUCCESS;
    }

    private function normalizeClassName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function isDirectoryEmpty(string $path): bool
    {
        $items = scandir($path);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // ignore dotfiles like .DS_Store
            if (str_starts_with($item, '.')) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
