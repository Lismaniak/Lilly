<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeQueryServiceCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:query:service');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a query service in Domains/<Domain>/Services/Queries')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('name', InputArgument::REQUIRED, 'Service name, e.g. ListUsers or ListUsersService');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeName((string) $input->getArgument('domain'));
        $serviceInput = $this->normalizeName((string) $input->getArgument('name'));

        if ($domain === '' || $serviceInput === '') {
            $output->writeln('<error>Usage: shape:domain:query:service <Domain> <ServiceName></error>');
            $output->writeln('<comment>Example: shape:domain:query:service Users ListUsers</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected folder src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $serviceName = $this->ensureServiceSuffix($serviceInput);
        $baseName = $this->stripServiceSuffix($serviceName);

        if ($baseName === '') {
            $output->writeln('<error>Invalid service name. Use letters/numbers only.</error>');
            return Command::FAILURE;
        }

        $servicesDir = "{$domainRoot}/Services/Queries";
        $this->mkdir($servicesDir, $output);

        $path = "{$servicesDir}/{$serviceName}.php";
        if (is_file($path)) {
            $output->writeln("<error>Query service already exists:</error> Domains\\{$domain}\\Services\\Queries\\{$serviceName}");
            return Command::FAILURE;
        }

        $contents = $this->stub(
            domain: $domain,
            baseName: $baseName,
            serviceName: $serviceName
        );

        file_put_contents($path, $contents);
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln("<info>Created query service:</info> {$serviceName}");
        $output->writeln("<info>Class:</info> Domains\\{$domain}\\Services\\Queries\\{$serviceName}");

        return Command::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]/', '', trim($name)) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function ensureServiceSuffix(string $name): string
    {
        $lower = strtolower($name);
        if (str_ends_with($lower, 'service')) {
            $base = substr($name, 0, -7);
            return $base . 'Service';
        }

        return $name . 'Service';
    }

    private function stripServiceSuffix(string $name): string
    {
        if (!str_ends_with(strtolower($name), 'service')) {
            return $name;
        }

        return substr($name, 0, -7);
    }

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($path));
        }
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }

    private function stub(string $domain, string $baseName, string $serviceName): string
    {
return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Services\\Queries;\n\nuse Lilly\\Dto\\QueryDto;\nuse Lilly\\Dto\\ResultDto;\nuse Lilly\\Services\\QueryService;\nuse Lilly\\Validation\\ArrayValidator;\n\nreadonly class {$baseName}Query implements QueryDto\n{\n    public function __construct()\n    {\n    }\n}\n\nreadonly class {$baseName}Result implements ResultDto\n{\n    /**\n     * @param list<mixed> \$items\n     */\n    public function __construct(array \$items = [])\n    {\n        \$this->items = ArrayValidator::mapListWithSchema(\$items, [\n            // TODO: add item schema.\n        ]);\n    }\n\n    /**\n     * @var list<array<string, mixed>>\n     */\n    public array \$items;\n}\n\nfinal class {$serviceName} extends QueryService\n{\n    protected function execute(QueryDto \$query): ResultDto\n    {\n        return new {$baseName}Result();\n    }\n}\n";
    }
}
