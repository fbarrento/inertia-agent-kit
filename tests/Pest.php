<?php

declare(strict_types=1);

use Tests\TestCase;

require_once __DIR__.'/Utils/HandoffTestFunctionHooks.php';
require_once __DIR__.'/Utils/InitTestFunctionHooks.php';
require_once __DIR__.'/Utils/ScaffoldingTestFunctionHooks.php';

uses(TestCase::class)->in('Feature');
