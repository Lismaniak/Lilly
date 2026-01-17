<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveCrossComponentCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:cross:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove an existing cross-domain component from src/App/CrossComponents')
            ->addArgument('name', InputArgument::REQUIRED, 'CrossComponent name, e.g. InviteUserToTeam');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->normalizeName((string) $input->getArgument('name'));

        if ($name === '') {
            $output->writeln('<error>Usage: shape:cross:remove <Name></error>');
            return Command::FAILURE;
        }

        $crossBase = "{$this->projectRoot}/src/App/CrossComponents";

        if (!is_dir($crossBase)) {
            $output->writeln('<error>No cross components folder found: src/App/CrossComponents</error>');
            return Command::FAILURE;
        }

        $matches = $this->findCrossComponentMatches($crossBase, $name);

        if (count($matches) === 0) {
            $output->writeln("<error>CrossComponent not found:</error> {$name}");
            return Command::FAILURE;
        }

        if (count($matches) > 1) {
            $output->writeln("<error>Ambiguous CrossComponent name '{$name}'. Found in:</error>");
            foreach ($matches as $m) {
                $output->writeln(" - " . $this->rel($m['componentPath']));
            }
            $output->writeln("<comment>Rename the component or delete manually for now.</comment>");
            return Command::FAILURE;
        }

        $componentPath = $matches[0]['componentPath'];
        $groupPath = $matches[0]['groupPath'];
        $groupName = $matches[0]['groupName'];

        $expectedBase = realpath($crossBase);
        $realComponent = realpath($componentPath);

        if ($expectedBase === false || $realComponent === false || !str_starts_with($realComponent, $expectedBase)) {
            $output->writeln('<error>Refusing to delete outside src/App/CrossComponents</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This will permanently delete the cross component '{$groupName}/{$name}'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        $this->deleteDirectory($componentPath);

        // 1) If group folder is now empty, delete it too.
        if ($this->isDirectoryEmpty($groupPath)) {
            rmdir($groupPath);
            $output->writeln(" + dir  removed " . $this->rel($groupPath));
        }

        // 2) If CrossComponents folder is now empty, delete it too.
        if ($this->isDirectoryEmpty($crossBase)) {
            rmdir($crossBase);
            $output->writeln(" + dir  removed " . $this->rel($crossBase));
        }

        // 3) If App folder is now empty, delete it too.
        $appBase = "{$this->projectRoot}/src/App";
        if (is_dir($appBase) && $this->isDirectoryEmpty($appBase)) {
            rmdir($appBase);
            $output->writeln(" + dir  removed " . $this->rel($appBase));
        }

        $output->writeln("<info>Removed cross component:</info> {$groupName}/{$name}");
        return Command::SUCCESS;
    }

    private function normalizeName(string $raw): string
    {
        $raw = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';
        return $raw !== '' ? ucfirst($raw) : '';
    }

    /**
     * Finds matches by scanning:
     * src/App/CrossComponents/<Group>/<ComponentName>
     *
     * @return list<array{groupName: string, groupPath: string, componentPath: string}>
     */
    private function findCrossComponentMatches(string $base, string $componentName): array
    {
        $items = scandir($base);
        if ($items === false) {
            return [];
        }

        $matches = [];

        foreach ($items as $group) {
            if ($group === '.' || $group === '..') {
                continue;
            }

            if (str_starts_with($group, '.')) {
                continue;
            }

            $groupPath = $base . '/' . $group;

            if (!is_dir($groupPath)) {
                continue;
            }

            $componentPath = $groupPath . '/' . $componentName;

            if (is_dir($componentPath)) {
                $matches[] = [
                    'groupName' => $group,
                    'groupPath' => $groupPath,
                    'componentPath' => $componentPath,
                ];
            }
        }

        return $matches;
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

            // Ignore common dotfiles like .DS_Store so cleanup works on macOS
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
