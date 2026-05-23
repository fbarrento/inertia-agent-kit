<?php

declare(strict_types=1);

use Tests\Utils\BoostResource;

test('ships package boost resource files', function (): void {
    expect(BoostResource::path('resources/boost/guidelines/core.blade.php'))->toBeFile()
        ->and(BoostResource::path('resources/boost/skills/inertia-agent-kit/SKILL.md'))->toBeFile();
});

test('suggests laravel boost as the agent substrate package', function (): void {
    $composer = json_decode(
        file_get_contents(BoostResource::path('composer.json')) ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['suggest']['laravel/boost'] ?? null)
        ->toContain('guidelines and skills');
});

test('keeps the boost resources concise', function (): void {
    $guideline = BoostResource::contents('resources/boost/guidelines/core.blade.php');
    $words = preg_split('/\s+/', trim($guideline)) ?: [];
    $lines = preg_split('/\R/', trim($guideline)) ?: [];
    $skill = BoostResource::contents('resources/boost/skills/inertia-agent-kit/SKILL.md');
    $skillWords = preg_split('/\s+/', trim($skill)) ?: [];
    $skillLines = preg_split('/\R/', trim($skill)) ?: [];

    expect(count($words))->toBeLessThanOrEqual(260)
        ->and(count($lines))->toBeLessThanOrEqual(40)
        ->and(count($skillWords))->toBeLessThanOrEqual(950)
        ->and(count($skillLines))->toBeLessThanOrEqual(160)
        ->and($guideline)->toContain('consult the `inertia-agent-kit` skill');
});

test('states the Boost and IAK boundary in each resource', function (string $path): void {
    $content = BoostResource::contents($path);

    expect($content)
        ->toContain('Boost')
        ->toContain('generic Laravel facts')
        ->toContain('IAK')
        ->toContain('Inertia/frontend')
        ->toContain('manifest')
        ->toContain('scaffolding')
        ->toContain('audit')
        ->toContain('feedback')
        ->toContain('verify')
        ->toContain('design-system')
        ->toContain('Storybook')
        ->toContain('browser logs');
})->with([
    'core guideline' => 'resources/boost/guidelines/core.blade.php',
    'IAK skill' => 'resources/boost/skills/inertia-agent-kit/SKILL.md',
]);

test('names the IAK artisan command surface', function (): void {
    $guideline = BoostResource::contents('resources/boost/guidelines/core.blade.php');
    $skill = BoostResource::contents('resources/boost/skills/inertia-agent-kit/SKILL.md');
    $combined = $guideline
        ."\n"
        .$skill;

    expect($combined)
        ->toContain('iak:init')
        ->toContain('iak:make-resource')
        ->toContain('iak:audit')
        ->toContain('iak:feedback')
        ->toContain('iak:verify')
        ->toContain('iak:handoff')
        ->toContain('--json')
        ->toContain('IAK_AGENT=1');

    expect($guideline)
        ->toContain('php artisan iak:handoff')
        ->toContain('do not paste large logs');

    expect($skill)
        ->toContain('php artisan iak:handoff create')
        ->toContain('php artisan iak:handoff validate')
        ->toContain('Do not paste large logs');
});

test('documents the flat package refactor guidance in boost resources', function (): void {
    $guideline = BoostResource::contents('resources/boost/guidelines/core.blade.php');
    $skill = BoostResource::contents('resources/boost/skills/inertia-agent-kit/SKILL.md');
    $combined = $guideline
        ."\n"
        .$skill;

    expect($combined)
        ->toContain('Laravel 12')
        ->toContain('13')
        ->toContain('src/Actions/*')
        ->toContain('one public `handle()`')
        ->toContain('constructor injection')
        ->toContain('src/Data/*')
        ->toContain('JsonSerializable')
        ->toContain('src/Enum/*')
        ->toContain('fixed vocabularies')
        ->toContain('private string const lists')
        ->toContain('src/Console/*')
        ->toContain('input/output')
        ->toContain('src/Support/*')
        ->toContain('reusable helpers')
        ->toContain('PHPStan at max level')
        ->toContain('Rector dry-run');
});

test('does not define generic MCP tool documentation', function (string $path): void {
    $content = BoostResource::contents($path);

    $forbiddenDefinitionPatterns = [
        '/^#{1,6}\s*(app info|application info|docs search|route list|database schema|database query|logs|browser logs|absolute url|last error)\b/mi',
        '/^\s*(tool|name)\s*:\s*(app[_-]?info|docs[_-]?search|route[_-]?list|database[_-]?(schema|query)|browser[_-]?logs|absolute[_-]?url|last[_-]?error)\b/mi',
        '/^\s*function\s+(appInfo|docsSearch|routeList|databaseSchema|databaseQuery|browserLogs|absoluteUrl|lastError)\b/m',
    ];

    foreach ($forbiddenDefinitionPatterns as $pattern) {
        expect((bool) preg_match($pattern, $content))->toBeFalse();
    }
})->with([
    'core guideline' => 'resources/boost/guidelines/core.blade.php',
    'IAK skill' => 'resources/boost/skills/inertia-agent-kit/SKILL.md',
]);

test('gives the IAK skill trigger focused frontmatter', function (): void {
    $skill = BoostResource::contents('resources/boost/skills/inertia-agent-kit/SKILL.md');

    expect((bool) preg_match('/\A---\R(?<frontmatter>.*?)\R---\R/s', $skill, $matches))->toBeTrue();

    $frontmatter = [];

    foreach (preg_split('/\R/', $matches['frontmatter']) ?: [] as $line) {
        [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
        $frontmatter[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    expect($frontmatter)
        ->toHaveKey('name', 'inertia-agent-kit')
        ->toHaveKey('description')
        ->and($frontmatter['description'])->toContain('Use when')
        ->and($frontmatter['description'])->toContain('Inertia UI')
        ->and($frontmatter['description'])->toContain('feedback');
});
