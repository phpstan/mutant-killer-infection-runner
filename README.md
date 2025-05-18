# Infection Static Analysis Plugin

This plugin is designed to run static analysis on top of [`infection/infection`](https://github.com/infection/infection)
test runs in order to discover if [escaped mutants](https://en.wikipedia.org/wiki/Mutation_testing)
are valid mutations, or if they do not respect the type signature of your
program. If the mutation would result in a type error, it is "killed".

TL;DR:

- This will improve your mutation score, since mutations which result in
  type errors become killed.
- This is very hacky, and replaces `vendor/bin/infection` essentially.
  Please read the `Stability` section below first for details.
- This is currently much slower than running infection by itself.
  There are ideas/suggestions to improve this in the future.

This plugin is a fork of excellent [Roave/infection-static-analysis-plugin](https://github.com/Roave/infection-static-analysis-plugin) and uses [PHPStan](https://phpstan.org/) instead of [Psalm](https://psalm.dev/).

## Usage

The current design of this tool requires you to run `vendor/bin/phpstan-mutant-killer-infection-runner`
instead of running `vendor/bin/infection`:

```sh
composer require --dev phpstan/mutant-killer-infection-runner

vendor/bin/phpstan-mutant-killer-infection-runner
```

### Configuration

The `phpstan-mutant-killer-infection-runner` binary accepts all of `infection` flags and arguments, and an additional `--phpstan-config` argument.

Using `--phpstan-config`, you can specify the [PHPStan configuration file](https://phpstan.org/config-reference) to use when analysing the generated mutations:

```sh
vendor/bin/roave-infection-static-analysis-plugin --phpstan-config phpstan.neon
```

## Background

If you come from a statically typed language with AoT compilers, you may be
confused about the scope of this project, but in the PHP ecosystem, producing
runnable code that does not respect the type system is very easy, and mutation
testing tools do this all the time.

Take for example following snippet:

```php
/**
 * @template T
 * @param array<T> $values
 * @return list<T>
 */
function makeAList(array $values): array
{
    return array_values($values);
}
```

Given a valid test as follows:

```php
function test_makes_a_list(): void
{
    $list = makeAList(['a' => 'b', 'c' => 'd']);
 
    assert(count($list) === 2);
    assert(in_array('b', $list, true));
    assert(in_array('d', $list, true));
}
```

The mutation testing framework will produce following mutation, since we
failed to verify the output in a more precise way:

```diff
/**
 * @template T
 * @param array<T> $values
 * @return list<T>
 */
function makeAList(array $values): array
{
-    return array_values($values);
+    return $values;
}
```

The code above is valid PHP, but not valid according to our type declarations.
While we can indeed write a test for this, such test would probably be
unnecessary, as existing type checkers can detect that our actual return value is
no longer a `list<T>`, but a map of `array<int|string, T>`, which is in conflict
with what we declared.

This plugin detects such mutations, and prevents them from making you write
unnecessary tests, leveraging the full power of [PHPStan](https://phpstan.org/).

## Stability

Since [`infection/infection`](https://github.com/infection/infection) is not yet
designed to support plugins, this tool uses a very aggressive approach to bootstrap
itself, and relies on internal details of the underlying runner.

To prevent compatibility issues, it therefore always pins to a very specific version
of `infection/infection`, so please be patient when you wish to use the latest and
greatest version of `infection/infection`, as we may still be catching up to it.

Eventually, we will contribute patches to `infection/infection` so that there is a
proper way to design and use plugins, without the need for dirty hacks.
