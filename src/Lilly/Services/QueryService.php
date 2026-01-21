<?php
declare(strict_types=1);

namespace Lilly\Services;

use Lilly\Dto\DtoGuard;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use InvalidArgumentException;

abstract class QueryService
{
    final public function handle(QueryDto $query): ResultDto
    {
        DtoGuard::assertQueryDto($query);
        $this->assertExpectedQuery($query);

        $result = $this->execute($query);

        DtoGuard::assertResultDto($result);
        $this->assertExpectedResult($result);

        return $result;
    }

    abstract protected function execute(QueryDto $query): ResultDto;

    protected function expectedQueryClass(): ?string
    {
        return null;
    }

    protected function expectedResultClass(): ?string
    {
        return null;
    }

    private function assertExpectedQuery(QueryDto $query): void
    {
        $expected = $this->expectedQueryClass();
        if ($expected === null) {
            return;
        }

        if (!$query instanceof $expected) {
            $actual = $query::class;
            throw new InvalidArgumentException("Expected query {$expected}, got {$actual}.");
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
