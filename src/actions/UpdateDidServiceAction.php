<?php

declare(strict_types=1);

namespace FairPulse\Actions;

use FairPulse\Core\ActionRuntime;
use FairPulse\Core\DidManagerBootstrap;
use FairPulse\Services\DidServiceUpdateService;

final class UpdateDidServiceAction
{
    public function __construct(private readonly ActionRuntime $runtime)
    {
    }

    public function run(): int
    {
        try {
            DidManagerBootstrap::requireAutoload($this->runtime->logger());

            $did = $this->runtime->env()->getRequired('DID', 'DID is required');
            $rotationPrivate = $this->runtime->env()->getRequired('ROTATION_PRIVATE', 'Rotation private key is required');
            $metadataUrl = $this->runtime->env()->getRequired('METADATA_URL', 'Metadata URL is required');
            $prevCid = $this->runtime->env()->get('PREV_CID');

            $service = new DidServiceUpdateService($this->runtime->logger());
            $service->update($did, $rotationPrivate, $metadataUrl, $prevCid);

            return 0;
        } catch (\Exception $exception) {
            $this->runtime->logger()->error('Could not update DID service: ' . $exception->getMessage());
            return 1;
        }
    }
}
