<?php

declare(strict_types=1);

namespace InertiaAgentKit\Feedback;

use InertiaAgentKit\Support\ProjectPaths;
use JsonException;

final class FeedbackEvidenceValidator
{
    /** @var array<int, string> */
    private const ALLOWED_SCHEMAS = [
        'iak.verify.v1',
        'iak.handoff.v1',
        'iak.feedback.resolution.v1',
    ];

    public function __construct(
        private readonly ProjectPaths $paths,
    ) {
    }

    /**
     * @return array{path: string, absolutePath: string, evidence: array<string, mixed>}
     */
    public function validate(string $path, string $feedbackId): array
    {
        $relativePath = $this->normalizeProjectRelativePath($path, $feedbackId);
        $absolutePath = $this->paths->basePath($relativePath);

        if (! is_file($absolutePath)) {
            throw new FeedbackException(
                'feedback.evidence_not_found',
                "Feedback resolution evidence [{$relativePath}] was not found.",
                2,
                $relativePath,
            );
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new FeedbackException(
                'feedback.evidence_unreadable',
                "Unable to read feedback resolution evidence [{$relativePath}].",
                3,
                $relativePath,
            );
        }

        try {
            $evidence = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FeedbackException(
                'feedback.evidence_invalid_json',
                "Feedback resolution evidence [{$relativePath}] is not valid JSON.",
                2,
                $relativePath,
                ['jsonError' => $exception->getMessage()],
            );
        }

        if (! is_array($evidence) || array_is_list($evidence)) {
            throw new FeedbackException(
                'feedback.evidence_invalid_json',
                "Feedback resolution evidence [{$relativePath}] must contain a JSON object.",
                2,
                $relativePath,
            );
        }

        $schema = $evidence['schema'] ?? null;

        if (! is_string($schema) || ! in_array($schema, self::ALLOWED_SCHEMAS, true)) {
            throw new FeedbackException(
                'feedback.evidence_invalid_schema',
                'Feedback resolution evidence must use an allowed IAK evidence schema.',
                2,
                $relativePath,
                [
                    'allowedSchemas' => self::ALLOWED_SCHEMAS,
                    'schema' => $schema,
                ],
            );
        }

        return [
            'path' => $relativePath,
            'absolutePath' => $absolutePath,
            'evidence' => $evidence,
        ];
    }

    private function normalizeProjectRelativePath(string $path, string $feedbackId): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw new FeedbackException(
                'feedback.evidence_required',
                'Resolving feedback requires an evidence path.',
                2,
            );
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            throw new FeedbackException(
                'feedback.evidence_invalid_path',
                'Feedback resolution evidence must be a project-relative path.',
                2,
                $path,
            );
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new FeedbackException(
                    'feedback.evidence_invalid_path',
                    'Feedback resolution evidence cannot contain path traversal segments.',
                    2,
                    $path,
                );
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        $feedbackResolutionPrefix = ".iak/feedback/{$feedbackId}/resolution/";

        if (! str_starts_with($normalized, '.iak/runs/') && ! str_starts_with($normalized, $feedbackResolutionPrefix)) {
            throw new FeedbackException(
                'feedback.evidence_invalid_path',
                'Feedback resolution evidence must live under .iak/runs or this feedback resolution directory.',
                2,
                $normalized,
                [
                    'allowedPrefixes' => [
                        '.iak/runs/',
                        $feedbackResolutionPrefix,
                    ],
                ],
            );
        }

        return $normalized;
    }
}
