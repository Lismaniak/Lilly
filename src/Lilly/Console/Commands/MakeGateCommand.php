<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeGateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:gate:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a Gate class in Domains/<Domain>/Policies/Gates')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('name', InputArgument::REQUIRED, 'Gate class name, e.g. CanViewUser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $gateClass = $this->normalizeClassName((string) $input->getArgument('name'));

        if ($domain === '' || $gateClass === '') {
            $output->writeln('<error>Usage: shape:gate:make <Domain> <GateClass></error>');
            $output->writeln('<comment>Example: shape:gate:make Users CanViewUser</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected folder src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $gatesDir = "{$domainRoot}/Policies/Gates";
        $this->mkdir($gatesDir, $output);

        $path = "{$gatesDir}/{$gateClass}.php";
        if (is_file($path)) {
            $output->writeln("<error>Gate already exists:</error> Domains\\{$domain}\\Policies\\Gates\\{$gateClass}");
            return Command::FAILURE;
        }

        $domainKey = strtolower($domain);
        $gateName = $this->inferGateName($domain, $gateClass);

        $contents = $this->stub($domain, $gateClass, $domainKey, $gateName);

        file_put_contents($path, $contents);
        $output->writeln(" + file " . $this->rel($path));

        $output->writeln("<info>Created gate:</info> {$gateName}");
        $output->writeln("<info>Class:</info> Domains\\{$domain}\\Policies\\Gates\\{$gateClass}");

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

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(" + dir  " . $this->rel($path));
        }
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }

    private function stub(string $domain, string $gateClass, string $domainKey, string $gateName): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Policies\\Gates;\n\nuse Lilly\\Http\\Request;\nuse Lilly\\Security\\Gate;\nuse Lilly\\Security\\PolicyDecision;\n\nfinal class {$gateClass} implements Gate\n{\n    public function name(): string\n    {\n        return '{$gateName}';\n    }\n\n    public function authorize(Request \$request): PolicyDecision\n    {\n        return PolicyDecision::allow();\n    }\n}\n";
    }

    private function inferGateName(string $domain, string $gateClass): string
    {
        $domainKey = strtolower($domain);
        $singular = $this->singularize($domain);

        $subject = $gateClass;

        if (str_starts_with($subject, 'Can')) {
            $subject = substr($subject, 3);
        }

        if ($singular !== '' && str_ends_with($subject, $singular)) {
            $subject = substr($subject, 0, -strlen($singular));
        }

        $subject = $subject !== '' ? $subject : $gateClass;

        $action = $this->camelToKebab($subject);

        return "{$domainKey}.{$action}";
    }

    private function camelToKebab(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value) ?? $value;
        return strtolower($value);
    }

    private function singularize(string $domain): string
    {
        // Simple rule for now: Users -> User
        if (str_ends_with($domain, 's') && strlen($domain) > 1) {
            return substr($domain, 0, -1);
        }

        return $domain;
    }
}
