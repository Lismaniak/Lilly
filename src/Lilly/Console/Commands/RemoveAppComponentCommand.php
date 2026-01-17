<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveAppComponentCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:component:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove an existing app component from src/App/Components')
            ->addArgument('name', InputArgument::REQUIRED, 'Component name, e.g. Ping');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->normalizeName((string) $input->getArgument('name'));

        if ($name === '') {
            $output->writeln('<error>Usage: shape:app:component:remove <Name></error>');
            return Command::FAILURE;
        }

        $componentsBase = "{$this->projectRoot}/src/App/Components";

        if (!is_dir($componentsBase)) {
            $output->writeln('<error>No app components folder found: src/App/Components</error>');
            return Command::FAILURE;
        }

        $componentPath = "{$componentsBase}/{$name}";

        if (!is_dir($componentPath)) {
            $output->writeln("<error>App component not found:</error> {$name}");
            return Command::FAILURE;
        }

        $expectedBase = realpath($componentsBase);
        $realComponent = realpath($componentPath);

        if ($expectedBase === false || $realComponent === false || !str_starts_with($realComponent, $expectedBase)) {
            $output->writeln('<error>Refusing to delete outside src/App/Components</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This will permanently delete the app component '{$name}'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        $this->deleteDirectory($componentPath);

        // If Components folder is now empty, delete it too.
        if ($this->isDirectoryEmpty($componentsBase)) {
            rmdir($componentsBase);
            $output->writeln(' + dir  removed ' . $this->rel($componentsBase));
        }

        // If App folder is now empty, delete it too.
        $appBase = "{$this->projectRoot}/src/App";
        if (is_dir($appBase) && $this->isDirectoryEmpty($appBase)) {
            rmdir($appBase);
            $output->writeln(' + dir  removed ' . $this->rel($appBase));
        }

        $output->writeln("<info>Removed app component:</info> {$name}");
        return Command::SUCCESS;
    }

    private function normalizeName(string $raw): string
    {
        $raw = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';
        return $raw !== '' ? ucfirst($raw) : '';
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

            if (str_starts_with($item, '.')) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;

            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath);
                continue;
            }

            unlink($fullPath);
        }

        rmdir($path);
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
