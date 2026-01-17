<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeAppGateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:gate:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create an App gate class in src/App/Policies/Gates')
            ->addArgument('name', InputArgument::REQUIRED, 'Gate class name, e.g. CanAccessAdmin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $gateClass = $this->normalizeClassName((string) $input->getArgument('name'));

        if ($gateClass === '') {
            $output->writeln('<error>Usage: shape:app:gate:make <GateClass></error>');
            return Command::FAILURE;
        }

        $gatesDir = "{$this->projectRoot}/src/App/Policies/Gates";
        if (!is_dir($gatesDir)) {
            $output->writeln('<error>App policies folder missing.</error> Run: shape:app:policy:make');
            return Command::FAILURE;
        }

        $path = "{$gatesDir}/{$gateClass}.php";
        if (is_file($path)) {
            $output->writeln("<error>App gate already exists:</error> App\\Policies\\Gates\\{$gateClass}");
            return Command::FAILURE;
        }

        $gateName = $this->inferGateName($gateClass);

        file_put_contents($path, $this->stub($gateClass, $gateName));
        $output->writeln(' + file ' . $this->rel($path));

        $output->writeln("<info>Created app gate:</info> {$gateName}");
        $output->writeln("<info>Class:</info> App\\Policies\\Gates\\{$gateClass}");

        return Command::SUCCESS;
    }

    private function normalizeClassName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function stub(string $gateClass, string $gateName): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Policies\\Gates;\n\nuse Lilly\\Http\\Request;\nuse Lilly\\Security\\Gate;\nuse Lilly\\Security\\PolicyDecision;\n\nfinal class {$gateClass} implements Gate\n{\n    public function name(): string\n    {\n        return '{$gateName}';\n    }\n\n    public function authorize(Request \$request): PolicyDecision\n    {\n        return PolicyDecision::allow();\n    }\n}\n";
    }

    private function inferGateName(string $gateClass): string
    {
        $subject = $gateClass;

        if (str_starts_with($subject, 'Can')) {
            $subject = substr($subject, 3);
        }

        $action = $this->camelToKebab($subject !== '' ? $subject : $gateClass);

        return "app.{$action}";
    }

    private function camelToKebab(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value) ?? $value;
        return strtolower($value);
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
