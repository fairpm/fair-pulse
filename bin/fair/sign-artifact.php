#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FairPulse\Actions\SignArtifactAction;
use FairPulse\Core\ActionRuntime;

$action = new SignArtifactAction(new ActionRuntime());
exit($action->run());
