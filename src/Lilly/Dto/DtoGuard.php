<?php
declare(strict_types=1);

namespace Lilly\Dto;

use ReflectionClass;
use RuntimeException;

final class DtoGuard
{
    public static function assertQueryDto(QueryDto $dto): void
    {
        self::assertReadonly($dto);
        self::assertSuffix($dto, 'Query');
    }

    public static function assertCommandDataDto(CommandDataDto $dto): void
    {
        self::assertReadonly($dto);
        self::assertSuffix($dto, 'Data');
    }

    public static function assertResultDto(ResultDto $dto): void
    {
        self::assertReadonly($dto);
        self::assertSuffix($dto, 'Result');
    }

    public static function assertActionInputDto(ActionInputDto $dto): void
    {
        self::assertReadonly($dto);
        self::assertSuffix($dto, 'Input');
    }

    public static function assertPropsDto(PropsDto $dto): void
    {
        self::assertReadonly($dto);
        self::assertSuffix($dto, 'Props');
    }

    private static function assertReadonly(object $dto): void
    {
        $ref = new ReflectionClass($dto);
        if (!$ref->isReadOnly()) {
            throw new RuntimeException('DTO must be readonly: ' . $ref->getName());
        }
    }

    private static function assertSuffix(object $dto, string $suffix): void
    {
        $ref = new ReflectionClass($dto);
        $short = $ref->getShortName();

        if (!str_ends_with($short, $suffix)) {
            throw new RuntimeException(
                "DTO class name must end with '{$suffix}': " . $ref->getName()
            );
        }
    }
}
