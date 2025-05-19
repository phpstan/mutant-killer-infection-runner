<?php

declare(strict_types=1);

namespace PHPStan\InfectionStaticAnalysis\PHPStan;

use Infection\Mutant\Mutant;
use JsonException;
use function array_key_exists;
use function escapeshellarg;
use function exec;
use function implode;
use function json_decode;
use const JSON_THROW_ON_ERROR;

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
			'--no-progress',
		];

		if ($this->configuration !== null) {
			$commandsParts[] = '--configuration';
			$commandsParts[] = escapeshellarg($this->configuration);
		}

		$commandsParts[] = '--tmp-file';
		$commandsParts[] = escapeshellarg($mutant->getFilePath());
		$commandsParts[] = '--instead-of';
		$commandsParts[] = escapeshellarg($mutant->getMutation()->getOriginalFilePath());

		$descriptorspec = [
			1 => ['pipe', 'w'], // stdout
			2 => ['pipe', 'w'], // stderr
		];

		$process = proc_open(implode(' ', $commandsParts), $descriptorspec, $pipes);
		if (is_resource($process)) {
			$stdout = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			$exitCode = proc_close($process);
		} else {
			return true;
		}

		if ($exitCode === 0) {
			return true;
		}

		try {
			$json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return true;
		}

		return $json['totals']['file_errors'] === 0;
    }
}
