<?php
declare(strict_types=1);

namespace Lilly\Services;

use Lilly\Dto\CommandDataDto;
use Lilly\Dto\DtoGuard;
use Lilly\Dto\ResultDto;
use InvalidArgumentException;

abstract class CommandService
{
    final public function handle(CommandDataDto $data): ResultDto
    {
        DtoGuard::assertCommandDataDto($data);
        $this->assertExpectedData($data);

        $result = $this->execute($data);

        DtoGuard::assertResultDto($result);
        $this->assertExpectedResult($result);

        return $result;
    }

    abstract protected function execute(CommandDataDto $data): ResultDto;

    protected function expectedDataClass(): ?string
    {
        return null;
    }

    protected function expectedResultClass(): ?string
    {
        return null;
    }

    private function assertExpectedData(CommandDataDto $data): void
    {
        $expected = $this->expectedDataClass();
        if ($expected === null) {
            return;
        }

        if (!$data instanceof $expected) {
            $actual = $data::class;
            throw new InvalidArgumentException("Expected command data {$expected}, got {$actual}.");
        }
    }

    private function assertExpectedResult(ResultDto $result): void
    {
        $expected = $this->expectedResultClass();
        if ($expected === null) {
            return;
        }

        if (!$result instanceof $expected) {
            $actual = $result::class;
            throw new InvalidArgumentException("Expected result {$expected}, got {$actual}.");
        }
    }
}
