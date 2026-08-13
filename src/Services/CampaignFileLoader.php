<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\Enums\CampaignType;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads and validates file-based campaign definitions from YAML files.
 * Two shapes share the format: a drip sequence (default, 'steps' required)
 * and a blast ('type: blast', a single subject/view, no steps).
 */
class CampaignFileLoader
{
    private const REQUIRED_CAMPAIGN_FIELDS = ['name', 'from_name', 'from_email'];
    private const REQUIRED_DRIP_FIELDS     = ['steps'];
    private const REQUIRED_BLAST_FIELDS    = ['subject', 'view'];
    private const REQUIRED_STEP_FIELDS     = ['position', 'subject', 'view', 'delay_hours'];

    public function __construct(private readonly string $campaignsPath = '')
    {
    }

    /**
     * Returns the resolved base directory for campaign YAML files.
     */
    public function resolvedPath(): string
    {
        return $this->campaignsPath !== ''
            ? rtrim($this->campaignsPath, '/')
            : rtrim(APPPATH, '/') . '/courier/campaigns';
    }

    /**
     * Validates parsed campaign data. Returns a list of error strings; empty means valid.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public function validate(array $data, string $filename): array
    {
        $errors = [];

        foreach (self::REQUIRED_CAMPAIGN_FIELDS as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                $errors[] = "{$filename}: missing required field '{$field}'";
            }
        }

        if (isset($data['from_email']) && $data['from_email'] !== '' && ! filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "{$filename}: 'from_email' is not a valid email address";
        }

        $type = CampaignType::tryFrom((string) ($data['type'] ?? CampaignType::DripSequence->value));

        if ($type === null) {
            $errors[] = "{$filename}: invalid type '{$data['type']}'; expected 'blast' or 'drip_sequence'";

            return $errors;
        }

        if ($type === CampaignType::Blast) {
            foreach (self::REQUIRED_BLAST_FIELDS as $field) {
                if (! isset($data[$field]) || $data[$field] === '') {
                    $errors[] = "{$filename}: missing required field '{$field}'";
                }
            }

            return $errors;
        }

        foreach (self::REQUIRED_DRIP_FIELDS as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                $errors[] = "{$filename}: missing required field '{$field}'";
            }
        }

        if (isset($data['steps']) && is_array($data['steps'])) {
            $positions = [];

            foreach ($data['steps'] as $i => $step) {
                $stepLabel = "{$filename}: step[{$i}]";

                foreach (self::REQUIRED_STEP_FIELDS as $field) {
                    if (! isset($step[$field]) || $step[$field] === '') {
                        $errors[] = "{$stepLabel}: missing required field '{$field}'";
                    }
                }

                if (isset($step['position'])) {
                    if (in_array($step['position'], $positions, true)) {
                        $errors[] = "{$stepLabel}: duplicate position '{$step['position']}'";
                    } else {
                        $positions[] = $step['position'];
                    }
                }
            }

            if ($positions !== [] && min($positions) !== 1) {
                $errors[] = "{$filename}: step positions must start at 1";
            }
        }

        return $errors;
    }

    /**
     * Loads and validates a single file. Returns ['errors' => string[], 'parsed' => array|null].
     * 'parsed' is null when there are errors.
     *
     * @return array{errors: list<string>, parsed: array<string, mixed>|null}
     */
    public function validateFile(string $filePath): array
    {
        $filename = basename($filePath);

        try {
            $parsed = $this->loadFromFile($filePath);
        } catch (RuntimeException $e) {
            return ['errors' => ["{$filename}: " . $e->getMessage()], 'parsed' => null];
        }

        $data   = array_merge($parsed['campaign'], ['steps' => $parsed['steps']]);
        $errors = $this->validate($data, $filename);

        return ['errors' => $errors, 'parsed' => $errors === [] ? $parsed : null];
    }

    /**
     * Scans the configured directory, validates every *.yaml file, and returns results keyed by basename.
     *
     * @return array<string, array{errors: list<string>, parsed: array<string, mixed>|null}>
     */
    public function validateAll(): array
    {
        $results = [];
        $files   = glob($this->resolvedPath() . '/*.yaml');

        foreach ($files !== false ? $files : [] as $filePath) {
            $results[basename($filePath)] = $this->validateFile($filePath);
        }

        return $results;
    }

    /**
     * Parses a YAML campaign file and returns ['campaign' => [...], 'steps' => [...]].
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException on YAML parse failure
     */
    public function loadFromFile(string $filePath): array
    {
        try {
            $data = Yaml::parseFile($filePath);
        } catch (ParseException $e) {
            throw new RuntimeException("Failed to parse campaign file '{$filePath}': " . $e->getMessage(), 0, $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException("Campaign file '{$filePath}' did not produce an array.");
        }

        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        return ['campaign' => $data, 'steps' => $steps];
    }

    /**
     * Loads a campaign by its source filename (relative to the configured campaigns directory).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException on YAML parse failure or if path escapes the campaigns directory
     */
    public function loadCampaignFile(string $sourceFile): array
    {
        $base     = $this->resolvedPath();
        $fullPath = $base . '/' . $sourceFile;
        $real     = realpath($fullPath);

        if ($real === false || ! str_starts_with($real, $base . '/')) {
            throw new RuntimeException("Invalid campaign source file: '{$sourceFile}'.");
        }

        return $this->loadFromFile($real);
    }

    /**
     * Loads all *.yaml files from the configured directory.
     *
     * @return array<string, array<string, mixed>> keyed by basename
     */
    public function loadAll(): array
    {
        $results = [];
        $files   = glob($this->resolvedPath() . '/*.yaml');

        foreach ($files !== false ? $files : [] as $file) {
            $results[basename($file)] = $this->loadFromFile($file);
        }

        return $results;
    }

    /**
     * Builds a DripStepDTO from parsed YAML step data. id is null (no DB row).
     *
     * @param array<string, mixed> $stepData
     */
    public function buildDripStepDTO(array $stepData, int $campaignId): DripStepDTO
    {
        $dto              = new DripStepDTO();
        $dto->id          = null;
        $dto->campaign_id = $campaignId;
        $dto->position    = (int) $stepData['position'];
        $dto->subject     = (string) $stepData['subject'];
        $dto->view        = (string) $stepData['view'];
        $dto->delay_hours = (int) $stepData['delay_hours'];
        $dto->created_at  = '';
        $dto->updated_at  = '';

        return $dto;
    }
}
