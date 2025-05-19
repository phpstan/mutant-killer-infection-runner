<?php

declare(strict_types=1);

namespace PHPStan\InfectionStaticAnalysisTest\PHPStan;

use Infection\Mutant\Mutant;
use Infection\Mutation\Mutation;
use Infection\Mutation\MutationAttributeKeys;
use Infection\Mutator\Arithmetic\Plus;
use Infection\PhpParser\MutatedNode;
use PHPUnit\Framework\TestCase;
use PHPStan\InfectionStaticAnalysis\PHPStan\RunStaticAnalysisAgainstMutant;
use Psr\Log\NullLogger;
use function array_combine;
use function array_map;
use function dirname;
use function file_put_contents;
use function Later\now;
use function Psl\Env\temp_dir;
use function Psl\Filesystem\create_temporary_file;
use function sprintf;
use function unlink;

/** @covers \PHPStan\InfectionStaticAnalysis\PHPStan\RunStaticAnalysisAgainstMutant */
final class RunStaticAnalysisAgainstMutantTest extends TestCase
{
    private RunStaticAnalysisAgainstMutant $runStaticAnalysis;

    /** @var list<string> */
    private array $generatedMutantFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->runStaticAnalysis = new RunStaticAnalysisAgainstMutant(
			new NullLogger(),
			dirname(__DIR__, 4),
			__DIR__ . '/project/phpstan.neon',
		);
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedMutantFiles as $mutatedFile) {
            unlink($mutatedFile);
        }

        $this->generatedMutantFiles = [];

        parent::tearDown();
    }

    public function testWillConsiderMutantValidIfNoErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            __DIR__ . '/project/src/validCode.php',
            <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a + $b;
}
PHP,
        )));
    }

    public function testWillConsiderMutantInvalidIfErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertFalse($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
			__DIR__ . '/project/src/validCode.php',
            <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a - $b;
}
PHP,
        )));
    }

    public function testWillConsiderMutantReferencingReflectionApiAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            __DIR__ . '/project/src/validCodeReflection.php',
            <<<'PHP'
<?php

function hasMethod(object $input, string $method): bool {
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
PHP,
        )));
    }

    /** @param non-empty-string $pathPrefix */
    private function makeMutant(
        string $originalFilePath,
        string $mutatedCode,
    ): Mutant {
        $mutatedCodePath = sprintf(__DIR__ . '/project/tmp-%s.php', md5(uniqid()));
        file_put_contents($mutatedCodePath, $mutatedCode);

        $this->generatedMutantFiles[] = $mutatedCodePath;

        return new Mutant(
            $mutatedCodePath,
            new Mutation(
                $originalFilePath,
                [],
                Plus::class,
                'test-mutator',
                array_combine(
                    MutationAttributeKeys::ALL,
                    array_map('strlen', MutationAttributeKeys::ALL),
                ),
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now($mutatedCode),
            now(''),
            now(''),
        );
    }
}
