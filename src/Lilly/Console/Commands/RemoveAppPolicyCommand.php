<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveAppPolicyCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:policy:remove');
    }

    protected function configure(): void
    {
        $this->setDescription('Remove App policy (and all app gates) from src/App/Policies');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policiesDir = "{$this->projectRoot}/src/App/Policies";
        $policyPath = "{$policiesDir}/AppPolicy.php";

        if (!is_dir($policiesDir)) {
            $output->writeln('<error>No app policies folder found:</error> src/App/Policies');
            return Command::FAILURE;
        }

        $realBase = realpath($policiesDir);
        if ($realBase === false) {
            $output->writeln('<error>Cannot resolve real path for:</error> src/App/Policies');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This will permanently delete AppPolicy and all app gates in 'src/App/Policies'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        // Delete AppPolicy.php if it exists
        if (is_file($policyPath)) {
            $realPolicy = realpath($policyPath);
            if ($realPolicy === false || !str_starts_with($realPolicy, $realBase)) {
                $output->writeln('<error>Refusing to delete outside src/App/Policies</error>');
                return Command::FAILURE;
            }

            unlink($policyPath);
            $output->writeln(' + file removed ' . $this->rel($policyPath));
        }

        // Delete Gates folder (and its contents) if it exists
        $gatesDir = "{$policiesDir}/Gates";
        if (is_dir($gatesDir)) {
            $realGates = realpath($gatesDir);
            if ($realGates === false || !str_starts_with($realGates, $realBase)) {
                $output->writeln('<error>Refusing to delete outside src/App/Policies</error>');
                return Command::FAILURE;
            }

            $this->deleteDirectory($gatesDir);
            $output->writeln(' + dir  removed ' . $this->rel($gatesDir));
        }

        // If Policies folder is now empty, delete it too
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

        $output->writeln('<info>Removed app policy and app gates:</info> src/App/Policies');
        return Command::SUCCESS;
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
