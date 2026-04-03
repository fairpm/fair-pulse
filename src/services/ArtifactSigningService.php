<?php

declare(strict_types=1);

namespace FairPulse\Services;

use FAIR\DID\Keys\EdDsaKey;
use FAIR\DID\PLC\PlcOperation;
use FairPulse\Models\SignatureResult;

final class ArtifactSigningService
{
    public function sign(string $verificationPrivate, string $artifactPath): SignatureResult
    {
        $verificationKey = EdDsaKey::from_private($verificationPrivate);
        $artifactContents = (string) file_get_contents($artifactPath);
        $hash = hash('sha384', $artifactContents, false);

        $signatureHex = $verificationKey->sign($hash);
        $signatureBinary = hex2bin($signatureHex);
        if ($signatureBinary === false) {
            throw new \RuntimeException('Could not decode hex signature.');
        }

        $signature = PlcOperation::base64url_encode($signatureBinary);
        $checksum = hash('sha256', $artifactContents);

        return new SignatureResult($signature, 'sha256:' . $checksum);
    }
}
