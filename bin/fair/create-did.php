#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use FairPulse\Actions\CreateDidAction;
use FairPulse\Core\ActionRuntime;

$action = new CreateDidAction(new ActionRuntime());
exit($action->run());
