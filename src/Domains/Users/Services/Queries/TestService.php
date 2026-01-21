<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Domains\Users\Repositories\UsersQueryRepository;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

readonly class TestQuery implements QueryDto
{
    public function __construct()
    {}
}

readonly class TestResult implements ResultDto
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public array $items = []
    ) {}
}

final class TestService extends QueryService
{
    public function __construct(
        private readonly UsersQueryRepository $users,
    ) {}

    /**
     * @return list<mixed>
     */
    public function list(
        TestQuery $query = new TestQuery()
    ): array
    {
        $result = $this->handle($query);
        return $result->items;
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new TestResult();
    }

    protected function expectedQueryClass(): ?string
    {
        return TestQuery::class;
    }

    protected function expectedResultClass(): ?string
    {
        return TestResult::class;
    }
}
