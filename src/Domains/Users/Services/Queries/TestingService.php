<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

readonly class TestingQuery implements QueryDto
{
    public function __construct()
    {}
}

readonly class TestingResult implements ResultDto
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public array $items = []
    ) {}
}

final class TestingService extends QueryService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {}

    /**
     * @return list<mixed>
     */
    public function list(
        TestingQuery $query = new TestingQuery()
    ): array
    {
        $result = $this->handle($query);
        return $result->items;
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new TestingResult();
    }

    protected function expectedQueryClass(): ?string
    {
        return TestingQuery::class;
    }

    protected function expectedResultClass(): ?string
    {
        return TestingResult::class;
    }
}
