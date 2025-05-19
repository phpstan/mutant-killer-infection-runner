<?php

declare(strict_types=1);

/**
 * @psalm-param positive-int $a
 * @psalm-param positive-int $b
 *
 * @psalm-return positive-int
 */
function add(int $a, int $b): int
{
    return $a + $b;
}
