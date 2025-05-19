<?php

declare(strict_types=1);

function hasMethod(object $input, string $method): bool
{
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
