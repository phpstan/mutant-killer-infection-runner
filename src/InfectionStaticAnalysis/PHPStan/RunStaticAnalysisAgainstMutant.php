<?php

declare(strict_types=1);

namespace PHPStan\InfectionStaticAnalysis\PHPStan;

use Infection\Mutant\Mutant;
use JsonException;
use Psr\Log\LoggerInterface;

use function escapeshellarg;
use function fclose;
use function implode;
use function is_resource;
use function json_decode;
use function json_encode;
use function microtime;
use function proc_close;
use function proc_open;
use function round;
use function sprintf;
use function stream_get_contents;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * @internal
 *
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 */
class RunStaticAnalysisAgainstMutant
{
    private static int $run = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectPath,
        private readonly string|null $configuration,
    ) {
    }

    public function isMutantStillValidAccordingToStaticAnalysis(Mutant $mutant): bool
    {
        $this->logger->debug(sprintf("Mutating file %s:\n\n%s\n", $mutant->getMutation()->getOriginalFilePath(), $mutant->getDiff()->get()));
        $commandsParts = [
            $this->projectPath . '/vendor/bin/phpstan',
            'analyse',
            '--error-format',
            'json',
            '--no-progress',
            '-vv',
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

        $nowTime = microtime(true);

        self::$run++;
        $this->logger->debug(sprintf('Running PHPStan - run #%d', self::$run));
        $process = proc_open(implode(' ', $commandsParts), $descriptorspec, $pipes);
        if (! is_resource($process)) {
            $this->logger->error('Could not run PHPStan');

            return true;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($stdout === false) {
            $this->logger->error('Could not read stdout');

            return true;
        }

        if ($stderr === false) {
            $this->logger->error('Could not read stderr');

            return true;
        }

        $elapsed = (int) round(microtime(true) - $nowTime, 2);
        $this->logger->debug($stderr);
        $this->logger->debug(sprintf('PHPStan exited with code %d after running for %.2f s', $exitCode, $elapsed));

        if ($exitCode === 0) {
            return true;
        }

        try {
            $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->logger->error(sprintf('Could not decode PHPStan JSON output: %s', $stdout));

            return true;
        }

        if ($json['totals']['file_errors'] === 0) {
            $this->logger->debug('Valid mutant: no file errors');

            return true;
        }

        $this->logger->debug(sprintf('Mutant killed: %s', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));

        return false;
    }
}
