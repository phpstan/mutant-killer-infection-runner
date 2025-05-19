<?php

declare(strict_types=1);

namespace unit\InfectionStaticAnalysisTest;

use Infection\Container;
use PHPStan\InfectionStaticAnalysis\Bootstrapper;
use PHPStan\InfectionStaticAnalysis\PHPStan\RunStaticAnalysisAgainstMutant;
use PHPStan\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant;
use PHPUnit\Framework\TestCase;

/**
 * @uses \PHPStan\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant
 *
 * @covers \PHPStan\InfectionStaticAnalysis\Bootstrapper
 */
final class BootstrapperTest extends TestCase
{
    public function testWillNotTestAnything(): void
    {
        $runStaticAnalysis = $this->createMock(RunStaticAnalysisAgainstMutant::class);
        Bootstrapper::bootstrap(
            Container::create(),
            $runStaticAnalysis,
        );

        self::assertInstanceOf(
            RunStaticAnalysisAgainstEscapedMutant::class,
            Bootstrapper::bootstrap(
                Container::create(),
                $runStaticAnalysis,
            )->getMutantExecutionResultFactory(),
        );
    }
}
