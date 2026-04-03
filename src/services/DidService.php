<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\PLC\PlcClient;
use FairPulse\Interfaces\LoggerInterface;
use FairPulse\Models\DidCreationResult;
use FairPulse\Utils\SummaryWriter;

final class DidService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SummaryWriter $summaryWriter,
    ) {
    }

    public function createOrReuse(
        string $rotationPrivate,
        string $verificationPrivate,
        bool $didExists,
        ?string $existingDid,
        string $repoUrl,
    ): DidCreationResult {
        if ($didExists && $existingDid !== null && $existingDid !== '') {
            $this->logger->notice("Using existing DID: {$existingDid}");
            return new DidCreationResult($existingDid, false, null);
        }

        $this->logger->notice('Creating new PLC DID...');

        $rotationKey = EcKey::from_private($rotationPrivate);
        $verificationKey = EdDsaKey::from_private($verificationPrivate);

        $handle = basename($repoUrl);
        $operation = DidCodec::create_plc_operation($rotationKey, $verificationKey, $handle);
        $signedOperation = DidCodec::sign_plc_operation($operation, $rotationKey);

        $did = DidCodec::generate_plc_did($signedOperation);
        $cid = $signedOperation->get_cid();

        $this->logger->notice("Generated DID: {$did}");
        $this->logger->notice("Operation CID: {$cid}");

        $summary = "\n## DID Generated\n\n";
        $summary .= "Your plugin's DID has been created:\n\n";
        $summary .= "```\n{$did}\n```\n\n";
        $summary .= "### Action Required\n\n";
        $summary .= "You must save this DID as a repository **variable** (not secret):\n\n";
        $summary .= "1. Go to: **Settings** → **Secrets and variables** → **Actions** → **Variables** tab\n";
        $summary .= "2. Click **New repository variable**\n";
        $summary .= "3. Name: `FAIR_DID`\n";
        $summary .= "4. Value: `{$did}`\n";
        $summary .= "5. Click **Add variable**\n\n";
        $summary .= "This is only needed once. Future publishes will use this stored DID.\n";
        $this->summaryWriter->append($summary);

        $this->logger->raw("\n");
        $this->logger->raw("╔═══════════════════════════════════════════════════════════════════╗\n");
        $this->logger->raw("║  ACTION REQUIRED: Save Your DID as a GitHub Variable          ║\n");
        $this->logger->raw("╚═══════════════════════════════════════════════════════════════════╝\n");
        $this->logger->raw("\n");
        $this->logger->raw("Your DID has been created: {$did}\n\n");
        $this->logger->raw("To complete setup and enable future publishes, add this as a VARIABLE:\n\n");
        $this->logger->raw("1. Go to: Settings → Secrets and variables → Actions → Variables tab\n");
        $this->logger->raw("2. Click 'New repository variable'\n");
        $this->logger->raw("3. Name: FAIR_DID\n");
        $this->logger->raw("4. Value: {$did}\n");
        $this->logger->raw("5. Click 'Add variable'\n\n");
        $this->logger->raw("WARNING: Use VARIABLES (not Secrets) - DIDs contain special characters.\n\n");
        $this->logger->raw("This step is only needed once. Future publishes will use this DID.\n\n");

        $client = new PlcClient();
        try {
            $response = $client->create_did($did, (array) $signedOperation->jsonSerialize());
            $this->logger->notice('DID submitted to PLC directory successfully');
            if (!empty($response)) {
                $this->logger->notice('PLC Response: ' . json_encode($response));
            }
        } catch (\Exception $exception) {
            $this->logger->warning('Could not submit to PLC directory: ' . $exception->getMessage());
            $this->logger->notice('DID can still be used locally');
        }

        return new DidCreationResult($did, true, $cid);
    }
}
