<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

final class BrokenWrongSuffixQueryService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new UsersLookup(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new BrokenWrongSuffixQueryResult('ok');
    }
}

readonly class UsersLookup implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

readonly class BrokenWrongSuffixQueryResult implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}
