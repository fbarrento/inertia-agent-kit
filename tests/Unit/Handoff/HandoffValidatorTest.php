<?php

declare(strict_types=1);

use InertiaAgentKit\Handoff\HandoffValidator;
use Tests\Utils\HandoffPayloadFixture;
use Tests\Utils\HandoffValidatorTestSupport;

require_once __DIR__.'/../../Utils/HandoffTestFunctionHooks.php';

test('accepts a valid completed handoff payload', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload();

    expect($result['valid'])->toBeTrue()
        ->and($result['status'])->toBe('valid')
        ->and($result['errors'])->toBe([])
        ->and($result['nextActions'])->toBe([]);
});

test('requires scalar status to be string when present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'status' => 1,
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.status.invalid');
});

test('reports an invalid changedFiles type', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [['page' => [
            'path' => 'resources/js/pages/index.tsx',
            'action' => 'create',
        ]]],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.invalid_type');
});

test('reports invalid changed file role and malformed role entries', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'not-a-valid-role' => [
                [
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                    'action' => 'create',
                ],
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.role_invalid');
});

test('reports invalid changed file entries and path/action problems', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => [
                ['action' => 'create'],
                ['path' => 99],
                ['path' => '../outside.ts', 'action' => 'invalid'],
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.path_missing')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.path_invalid')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.action_invalid');
});

test('rejects empty changed files for completed handoffs', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => [],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.empty');
});

test('accepts no errors when handoff is blocked and still validates artifacts', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'status' => 'blocked',
        'errors' => [
            ['code' => 'run-blocked'],
            ['code' => 'ci-failed'],
        ],
        'nextActions' => [],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

test('requires valid evidence object when completed and validates resolved status', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'status' => 'completed',
        'evidence' => 'invalid',
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.evidence.invalid_type');
});

test('reports missing and non-integer feedback unresolved fields', function (): void {
    $missing = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'evidence' => [
            'audit' => [
                'status' => 'passed',
            ],
            'tests' => [
                'status' => 'passed',
            ],
            'feedback' => [],
        ],
    ]);

    $negative = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'evidence' => [
            'audit' => [
                'status' => 'passed',
            ],
            'tests' => [
                'status' => 'passed',
            ],
            'feedback' => [
                'unresolved' => -2,
            ],
        ],
    ]);

    expect($missing['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($missing))->toContain('handoff.evidence.feedback_unresolved_missing')
        ->and($negative['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($negative))->toContain('handoff.evidence.feedback_unresolved_invalid');
});

test('validates artifact references and file existence', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_01/missing.json',
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.missing');
});

test('rejects artifact references outside allowed locations', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(function (array $payload, string $basePath): array {
        HandoffValidatorTestSupport::writeFile($basePath, 'storage/handoff.json', '{}');

        return [
            ...$payload,
            'artifacts' => [
                'handoff' => [
                    'kind' => 'json',
                    'path' => 'storage/handoff.json',
                ],
            ],
        ];
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.path_not_allowed');
});

test('rejects invalid terminal artifact nodes', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'artifacts' => [
            'handoff' => [
                'status' => 'not_json',
            ],
            'screenshot' => ['artifact', 'value'],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.kind_missing')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.path_missing');
});

test('flags invalid notes and oversized note', function (): void {
    $long = str_repeat('a', 301);

    $invalidType = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'notes' => 'all in one',
    ]);

    $invalidValue = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'notes' => [
            12,
            $long,
        ],
    ]);

    expect($invalidType['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($invalidType))->toContain('handoff.notes.invalid_type')
        ->and($invalidValue['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($invalidValue))->toContain('handoff.notes.invalid')
        ->and(HandoffValidatorTestSupport::errorCodes($invalidValue))->toContain('handoff.notes.too_long');
});

test('validates next actions and error payload for completed handoffs', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'nextActions' => [
            'invalid',
            ['type' => 'fix', 'blocking' => null],
        ],
        'errors' => [
            ['code' => 'x'],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.next_actions.invalid')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.errors.present');
});

test('returns filtered nextActions in the validation result', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'nextActions' => [
            ['type' => 'follow_up', 'summary' => 'Fix issue', 'blocking' => false],
            null,
            ['summary' => ''],
        ],
    ]);

    expect($result['nextActions'])->toEqual([
        ['type' => 'follow_up', 'summary' => 'Fix issue', 'blocking' => false],
        ['summary' => ''],
    ]);
});

test('rejects completed handoff when required artifact paths are outside base via symlink', function (): void {
    $basePath = HandoffPayloadFixture::makeBasePath();

    try {
        $payload = HandoffPayloadFixture::validPayload($basePath);
        $artifactPath = $basePath.'/.iak/runs/run_01/escape.json';
        $outsidePath = sys_get_temp_dir().'/iak-escape-target-'.bin2hex(random_bytes(6));
        file_put_contents($outsidePath, '{}');
        symlink($outsidePath, $artifactPath);

        $payload['artifacts']['handoff']['path'] = '.iak/runs/run_01/escape.json';

        $result = (new HandoffValidator)->validate($payload, $basePath);

        expect($result['valid'])->toBeFalse()
            ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.path_outside_base');
    } finally {
        if (isset($outsidePath) && file_exists($outsidePath)) {
            unlink($outsidePath);
        }

        if (isset($basePath)) {
            @unlink($basePath.'/.iak/runs/run_01/escape.json');
            HandoffPayloadFixture::removeDirectory($basePath);
        }
    }
});

test('uses path normalization for absolute/relative validations', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => [
                ['path' => 'resources/js/pages/index.tsx', 'action' => 'create'],
            ],
        ],
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak//runs/./run_01/handoff.json',
            ],
        ],
    ]);

    expect($result['valid'])->toBeTrue();
});

test('reports all required fields when the handoff payload is incomplete', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset(
            $payload['schema'],
            $payload['runId'],
            $payload['task'],
            $payload['status'],
            $payload['summary'],
            $payload['changedFiles'],
            $payload['evidence'],
            $payload['artifacts'],
            $payload['notes'],
            $payload['nextActions'],
            $payload['errors'],
        );

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.schema.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.run_id.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.task.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.status.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.summary.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.evidence.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifacts.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.notes.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.next_actions.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.errors.required');
});

test('accepts payloads without schema because schema uses a default fallback', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['schema']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.schema.required')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->not->toContain('handoff.schema.invalid');
});

test('flags an invalid schema identifier', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'schema' => 'iak.handoff.invalid',
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.schema.invalid');
});

test('covers changedFiles when the section is missing entirely', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['changedFiles']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.required');
});

test('validates malformed changed file role entries', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => 'not-a-list',
            'feature' => [
                'not-a-row',
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.entries_invalid')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.changed_files.entry_invalid');
});

test('validates malformed evidence status constraints', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'status' => 'completed',
        'evidence' => [
            'audit' => [],
            'tests' => ['status' => 'failed'],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.evidence.audit_status_missing')
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.evidence.tests_failed');
});

test('validates artifact structures and nested traversal', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'artifacts' => [
            'handoff' => 'bad leaf',
            'nested' => [
                'group' => [
                    'leaf' => [
                        'kind' => 'json',
                        'path' => '.iak/runs/run_01/handoff.json',
                    ],
                ],
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.invalid');
});

test('requires artifacts to be an object when present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'artifacts' => [
            'not-an-object',
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifacts.invalid_type');
});

test('validates artifact path invalidation and reference restrictions', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '../outside.ts',
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifact.path_invalid');
});

test('returns early when evidence is not present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['evidence']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.evidence.required');
});

test('returns early when artifacts are not present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['artifacts']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.artifacts.required');
});

test('returns early when notes are not present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['notes']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.notes.required');
});

test('returns early when nextActions are not present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['nextActions']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.next_actions.required');
});

test('returns early when errors are not present', function (): void {
    $result = HandoffValidatorTestSupport::validatePayload(static function (array $payload, string $basePath): array {
        unset($payload['errors']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.errors.required');
});

test('validates next actions, extracted payloads, and completed blocking rules', function (): void {
    $scalarResult = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'nextActions' => 'invalid-actions',
        'status' => 'completed',
    ]);

    expect($scalarResult['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($scalarResult))->toContain('handoff.next_actions.invalid_type')
        ->and($scalarResult['nextActions'])->toBe([]);

    $blockingResult = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'nextActions' => [['type' => 'follow_up', 'blocking' => true]],
        'status' => 'completed',
    ]);

    expect($blockingResult['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($blockingResult))->toContain('handoff.next_actions.blocking');
});

test('reports malformed errors payloads and validates completed errors contract', function (): void {
    $invalidErrorsType = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'status' => 'completed',
        'errors' => 'not-a-list',
    ]);

    expect($invalidErrorsType['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($invalidErrorsType))->toContain('handoff.errors.invalid_type');
});

test('covers path normalization branches in helper paths', function (): void {
    $absolutePath = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => [['path' => '/tmp/absolute.ts', 'action' => 'create']],
        ],
    ]);

    expect($absolutePath['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($absolutePath))->toContain('handoff.changed_files.path_invalid');

    $dotPath = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
        ...$payload,
        'changedFiles' => [
            'page' => [['path' => '.', 'action' => 'create']],
        ],
    ]);

    expect($dotPath['valid'])->toBeFalse()
        ->and(HandoffValidatorTestSupport::errorCodes($dotPath))->toContain('handoff.changed_files.path_invalid');
});

test('falls back to strlen when mb_strlen is not forced for notes length', function (): void {
    $previousMb = getenv('I_AK_FORCE_MB_STRLEN_FALLBACK');

    try {
        putenv('I_AK_FORCE_MB_STRLEN_FALLBACK=1');

        $result = HandoffValidatorTestSupport::validatePayload(static fn (array $payload, string $basePath): array => [
            ...$payload,
            'notes' => [str_repeat('a', 301)],
        ]);

        expect($result['valid'])->toBeFalse()
            ->and(HandoffValidatorTestSupport::errorCodes($result))->toContain('handoff.notes.too_long');
    } finally {
        if ($previousMb === false) {
            putenv('I_AK_FORCE_MB_STRLEN_FALLBACK');
        } else {
            putenv('I_AK_FORCE_MB_STRLEN_FALLBACK='.$previousMb);
        }
    }
});
