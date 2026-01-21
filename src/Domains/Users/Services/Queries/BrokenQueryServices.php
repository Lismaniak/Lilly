<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

final class BrokenNonReadonlyQueryService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new NonReadonlyUserQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new ValidUsersResult('ok');
    }
}

final class BrokenWrongSuffixQueryService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new UsersLookup(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new ValidUsersResult('ok');
    }
}

final class BrokenNonReadonlyResultService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new ValidUsersQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new NonReadonlyUsersResult('bad');
    }
}

final class BrokenWrongSuffixResultService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new ValidUsersQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new UsersOutput('bad');
    }
}

class NonReadonlyUserQuery implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

readonly class UsersLookup implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

readonly class ValidUsersQuery implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

class NonReadonlyUsersResult implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}

readonly class UsersOutput implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}

readonly class ValidUsersResult implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}
