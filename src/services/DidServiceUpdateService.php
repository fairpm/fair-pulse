<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\EcKey;
use FAIR\DID\Keys\KeyFactory;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use FairPulse\Interfaces\LoggerInterface;

final class DidServiceUpdateService
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function update(string $did, string $rotationPrivate, string $metadataUrl, ?string $prevCid): void
    {
        $this->logger->notice('Reconstructing rotation key from private key...');
        $rotationKey = EcKey::from_private($rotationPrivate);
        $this->logger->notice('Rotation key reconstructed successfully');

        $this->logger->notice('Initializing PLC client...');
        $client = new PlcClient();
        $this->logger->notice('PLC client initialized');

        $this->logger->notice("Fetching current DID document for: {$did}");
        $currentDoc = $client->resolve_did($did);
        $this->logger->notice('DID document retrieved successfully');

        if ($prevCid !== null && $prevCid !== '') {
            $this->logger->notice("Using previous CID from create-did step: {$prevCid}");
        } else {
            $this->logger->notice('No previous CID provided, fetching from PLC directory...');
            $prevCid = $client->get_previous_cid($did);
            $this->logger->notice("Previous CID retrieved from PLC: {$prevCid}");
        }

        $this->logger->notice('Preserving rotation keys...');
        $rotationKeys = [$rotationKey];

        $this->logger->notice('Extracting verification methods...');
        $verificationMethods = [];
        $methodsData = $currentDoc['verificationMethod'] ?? [];
        $this->logger->notice('Found ' . count($methodsData) . ' verification methods in current document');

        foreach ($methodsData as $method) {
            $methodId = $method['id'] ?? '';
            $publicKeyMultibase = $method['publicKeyMultibase'] ?? '';
            if ($methodId === '' || $publicKeyMultibase === '') {
                continue;
            }

            $this->logger->notice("Decoding verification method: {$methodId}");
            $didKey = 'did:key:' . $publicKeyMultibase;
            $fragment = substr($methodId, strrpos($methodId, '#') + 1);
            $verificationMethods[$fragment] = KeyFactory::decode_did_key($didKey);
        }

        $this->logger->notice('Successfully decoded ' . count($verificationMethods) . ' verification methods');
        $this->logger->notice('Preserving alsoKnownAs...');
        $alsoKnownAs = $currentDoc['alsoKnownAs'] ?? [];

        $services = [
            'fairpm_repo' => [
                'type' => 'FairPackageManagementRepo',
                'endpoint' => $metadataUrl,
            ],
        ];
        $this->logger->notice('Service endpoint configured');

        $this->logger->group('Update Details');
        $this->logger->raw('Rotation Keys: ' . count($rotationKeys) . "\n");
        $this->logger->raw('Verification Methods: ' . count($verificationMethods) . "\n");
        $this->logger->raw('Also Known As: ' . json_encode($alsoKnownAs) . "\n");
        $this->logger->raw('Services: ' . json_encode($services, JSON_PRETTY_PRINT) . "\n");
        $this->logger->raw("Previous operation CID: {$prevCid}\n");
        $this->logger->endGroup();

        $operation = new PlcOperation(
            type: 'plc_operation',
            rotation_keys: $rotationKeys,
            verification_methods: $verificationMethods,
            also_known_as: $alsoKnownAs,
            services: $services,
            prev: $prevCid,
        );

        $this->logger->notice('Signing operation...');
        $signedOp = DidCodec::sign_plc_operation($operation, $rotationKey);

        $this->logger->group('Signed Operation');
        $this->logger->raw(json_encode((array) $signedOp->jsonSerialize(), JSON_PRETTY_PRINT) . "\n");
        $this->logger->endGroup();

        $this->logger->notice('Submitting update to PLC directory...');
        $client->update_did($did, (array) $signedOp->jsonSerialize());
        $this->logger->notice("DID updated with FAIR service endpoint: {$metadataUrl}");

        $this->logger->notice('Verifying update by fetching DID document again...');
        $updatedDoc = $client->resolve_did($did);
        $this->logger->notice('Updated DID document retrieved');

        $this->logger->group('Updated DID Document');
        $this->logger->raw(json_encode($updatedDoc, JSON_PRETTY_PRINT) . "\n");
        $this->logger->endGroup();

        $serviceCount = isset($updatedDoc['service']) ? count($updatedDoc['service']) : 0;
        $this->logger->notice("Services in updated document: {$serviceCount}");

        if (isset($updatedDoc['service']) && !empty($updatedDoc['service'])) {
            $this->logger->notice('Services array updated successfully');
            foreach ($updatedDoc['service'] as $service) {
                $serviceId = $service['id'] ?? 'unknown';
                $serviceType = $service['type'] ?? 'unknown';
                $this->logger->notice("Service found - ID: {$serviceId}, Type: {$serviceType}");
            }
            return;
        }

        $this->logger->warning('Services array is empty after update');
    }
}
