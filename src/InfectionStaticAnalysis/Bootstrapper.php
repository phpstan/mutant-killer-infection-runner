<?php

declare(strict_types=1);

namespace PHPStan\InfectionStaticAnalysis;

use Infection\Container;
use Infection\Mutant\MutantExecutionResultFactory;
use PHPStan\InfectionStaticAnalysis\PHPStan\RunStaticAnalysisAgainstMutant;
use ReflectionMethod;

/** @internal */
final class Bootstrapper
{
    public static function bootstrap(
        Container $container,
        RunStaticAnalysisAgainstMutant $runStaticAnalysis,
    ): Container {
        $reflectionOffsetSet = new ReflectionMethod(Container::class, 'offsetSet');

        $factory = static function (Container $container) use ($runStaticAnalysis): MutantExecutionResultFactory {
            return new RunStaticAnalysisAgainstEscapedMutant(
                new MutantExecutionResultFactory($container->getTestFrameworkAdapter()),
                $runStaticAnalysis,
            );
        };

        $reflectionOffsetSet->invokeArgs($container, [MutantExecutionResultFactory::class, $factory]);

        return $container;
    }
}
