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
use function array_combine;
use function array_map;
use function file_put_contents;
use function Later\now;
use function Psl\Filesystem\create_temporary_file;
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

        $validCode = <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a + $b;
}
PHP;

        $invalidCode = <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a - $b;
}
PHP;

        $validCodeReferencingProjectFiles = <<<'PHP'
<?php

function add(array $input): int {
    return count((new \Roave\InfectionStaticAnalysis\Stub\ArrayFilter())->makeAList($input));
}
PHP;

        $validCodeReferencingReflectionApi = <<<'PHP'
<?php

function hasMethod(object $input, string $method): bool {
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
PHP;

        $declaredClassSymbol = <<<'PHP'
<?php class DeclaredClassSymbol {}
PHP;

        $validCodePath                         = create_temporary_file(null, 'valid-code-');
        $invalidCodePath                       = create_temporary_file(null, 'invalid-code-');
        $validCodeReferencingProjectFilesPath  = create_temporary_file(null, 'valid-code-referencing-project-files-');
        $validCodeReferencingReflectionApiPath = create_temporary_file(null, 'valid-code-referencing-reflection-api-');
        $declaredClassSymbolPath               = create_temporary_file(null, 'declared-class-symbol-');
        $repeatedDeclaredClassSymbolPath       = create_temporary_file(null, 'repeated-declared-class-symbol-');

        file_put_contents($validCodePath, $validCode);
        file_put_contents($invalidCodePath, $invalidCode);
        file_put_contents($validCodeReferencingProjectFilesPath, $validCodeReferencingProjectFiles);
        file_put_contents($validCodeReferencingReflectionApiPath, $validCodeReferencingReflectionApi);
        file_put_contents($declaredClassSymbolPath, $declaredClassSymbol);
        file_put_contents($repeatedDeclaredClassSymbolPath, $declaredClassSymbol);

        $this->runStaticAnalysis = new RunStaticAnalysisAgainstMutant(
			dirname(__DIR__, 3),
			null,
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
            'valid-mutated-code-',
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
            'invalid-mutated-code-',
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

    public function testWillConsiderMutantReferencingProjectFilesAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'valid-code-referencing-project-files-',
            <<<'PHP'
<?php

function add(array $input): int {
    return count((new \Roave\InfectionStaticAnalysis\Stub\ArrayFilter())->makeAList($input));
}
PHP,
        )));
    }

    public function testWillConsiderMutantReferencingReflectionApiAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'valid-code-referencing-reflection-api-',
            <<<'PHP'
<?php

function hasMethod(object $input, string $method): bool {
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
PHP,
        )));
    }

    public function testWillConsiderMutantWithRepeatedClassSymbolDeclarationAsEscaped(): void
    {
        $declaresClassSymbol   = $this->makeMutant('declares-class-symbol-', '<?php class DeclaredClassSymbol {}');
        $reDeclaresClassSymbol = $this->makeMutant('re-declares-class-symbol-', '<?php class DeclaredClassSymbol {}');

        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($declaresClassSymbol),
            'Class symbol was seen for the first time ever - no static analysis issues - mutation is legit',
        );
        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($reDeclaresClassSymbol),
            'Class symbol was seen for the second time (on a new file) - no static analysis issues - mutation is legit',
        );
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-841174672 */
    public function testInternalPhpEngineConstantsCanBeReferencedFromAnalyzedMutantCode(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'reference-to-php-internal-constant-',
            '<?php return json_encode("foo", JSON_THROW_ON_ERROR);',
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-841174672 */
    public function testInternalPhpEngineSymbolPurityPropertiesCanBeReliedUponDuringMutantAnalysis(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-php-internal-class-purity-properties-',
            <<<'PHP'
<?php
/** @psalm-pure */
function pureTimestamp(\DateTimeImmutable $d): int {
    return $d->getTimestamp();
}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-843249180 */
    public function testReferencesToInternalStaticMethodsAreConsidered(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-php-internal-class-static-method-',
            <<<'PHP'
<?php
/** @psalm-mutation-free */
function makeDate(): ?\DateTimeInterface {
    return \DateTimeImmutable::createFromFormat('Y-m-d', '2020-01-01') ?: null;
}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-843436051 */
    public function testCanReferenceInternalStubInformationAfterScanningMutantWithNoCoreClassReferences(): void
    {
        $mutantWithNoPhpCoreReferences = $this->makeMutant(
            'mutant-with-no-php-core-references-',
            '<?php return 1;',
        );
        $mutantWithPhpCoreReferences   = $this->makeMutant(
            'mutant-with-php-core-references-',
            '<?php return DateTimeImmutable::createFromFormat("Y-m-d", "2021-05-18");',
        );

        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutantWithNoPhpCoreReferences));
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutantWithPhpCoreReferences));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-842687795 */
    public function testPreloadedStubsAreNotConsideredIfNotConfigured(): void
    {
        self::assertFalse($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-unknown-preloaded-stub-class-',
            <<<'PHP'
<?php 
class StubImplementation implements \Roave\InfectionStaticAnalysisAsset\PreloadClassStub\Stub {}
PHP,
        )));
    }

    /** @param non-empty-string $pathPrefix */
    private function makeMutant(
        string $pathPrefix,
        string $mutatedCode,
        string $originalFilePath = 'irrelevant',
    ): Mutant {
        $mutatedCodePath = create_temporary_file(null, $pathPrefix);
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
