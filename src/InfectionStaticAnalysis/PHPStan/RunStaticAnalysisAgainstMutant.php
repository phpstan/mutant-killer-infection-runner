<?php

declare(strict_types=1);

namespace PHPStan\InfectionStaticAnalysis\PHPStan;

use Infection\Mutant\Mutant;
use function array_key_exists;
use function escapeshellarg;
use function exec;
use function implode;
use function json_decode;

/**
 * @internal
 *
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 */
class RunStaticAnalysisAgainstMutant
{
    public function __construct(
		private readonly string $projectPath,
		private readonly ?string $configuration,
	)
    {
    }

    public function isMutantStillValidAccordingToStaticAnalysis(Mutant $mutant): bool
    {
		$commandsParts = [
			$this->projectPath . '/vendor/bin/phpstan',
			'analyse',
			'--error-format',
			'json',
		];

		if ($this->configuration !== null) {
			$commandsParts[] = '--configuration';
			$commandsParts[] = escapeshellarg($this->configuration);
		}

		$commandsParts[] = '--tmp-file';
		$commandsParts[] = escapeshellarg($mutant->getFilePath());
		$commandsParts[] = '--instead-of';
		$commandsParts[] = escapeshellarg($mutant->getMutation()->getOriginalFilePath());

		exec(implode(' ', $commandsParts), $outputLines, $exitCode);
		if ($exitCode === 0) {
			return true;
		}

		$json = json_decode(implode("\n", $outputLines), true);

		return !array_key_exists($mutant->getFilePath(), $json['files']);
    }
}
