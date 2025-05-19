<?php

function hasMethod(object $input, string $method): bool {
	return (new ReflectionClass($input))
		->hasMethod($method);
}
