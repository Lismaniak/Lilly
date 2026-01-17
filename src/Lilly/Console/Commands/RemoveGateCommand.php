<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class RemoveGateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:gate:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Remove a Gate class from Domains/<Domain>/Policies/Gates')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('name', InputArgument::REQUIRED, 'Gate class name, e.g. CanViewUser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $gateClass = $this->normalizeClassName((string) $input->getArgument('name'));

        if ($domain === '' || $gateClass === '') {
            $output->writeln('<error>Usage: shape:gate:remove <Domain> <GateClass></error>');
            return Command::FAILURE;
        }

        $gatePath = "{$this->projectRoot}/src/Domains/{$domain}/Policies/Gates/{$gateClass}.php";

        if (!is_file($gatePath)) {
            $output->writeln("<error>Gate does not exist:</error> Domains\\{$domain}\\Policies\\Gates\\{$gateClass}");
            return Command::FAILURE;
        }

        $realBase = realpath("{$this->projectRoot}/src/Domains/{$domain}/Policies/Gates");
        $realFile = realpath($gatePath);

        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            $output->writeln('<error>Refusing to delete outside Policies/Gates</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This will permanently delete gate '{$gateClass}' in domain '{$domain}'. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        unlink($gatePath);
        $output->writeln(" + file removed " . $this->rel($gatePath));
        $output->writeln("<info>Removed gate:</info> Domains\\{$domain}\\Policies\\Gates\\{$gateClass}");

        return Command::SUCCESS;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function normalizeClassName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
