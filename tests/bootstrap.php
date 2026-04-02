<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

putenv('FAIR_TEST_STUB_DID_MANAGER=true');
$_ENV['FAIR_TEST_STUB_DID_MANAGER'] = 'true';
$_SERVER['FAIR_TEST_STUB_DID_MANAGER'] = 'true';

function fairPulseInstallDidManagerStubs(): void
{
    $vendorDir = '/tmp/did-manager/vendor';
    if (!is_dir($vendorDir)) {
        mkdir($vendorDir, 0777, true);
    }

    $autoloadPath = $vendorDir . '/autoload.php';

    $stubCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace FAIR\DID\Keys {
    class EcKey
    {
        public function __construct(private string $private)
        {
        }

        public static function from_private(string $private): self
        {
            return new self($private);
        }

        public function encode_private(): string
        {
            return $this->private;
        }

        public function encode_public(): string
        {
            return 'pub-' . substr(hash('sha256', $this->private), 0, 24);
        }
    }

    class EdDsaKey
    {
        public function __construct(private string $private)
        {
        }

        public static function from_private(string $private): self
        {
            return new self($private);
        }

        public function encode_private(): string
        {
            return $this->private;
        }

        public function encode_public(): string
        {
            return 'pub-' . substr(hash('sha256', $this->private), 0, 24);
        }

        public function sign(string $message): string
        {
            return hash('sha256', $this->private . ':' . $message);
        }
    }

    class KeyFactory
    {
        public static function decode_did_key(string $didKey): EcKey
        {
            return new EcKey('decoded-' . $didKey);
        }
    }
}

namespace FAIR\DID\PLC {
    class PlcOperation implements \JsonSerializable
    {
        private array $data;
        private string $cid;

        public function __construct(
            string $type = 'plc_operation',
            array $rotation_keys = [],
            array $verification_methods = [],
            array $also_known_as = [],
            array $services = [],
            ?string $prev = null,
            ?string $handle = null
        ) {
            $this->data = [
                'type' => $type,
                'rotationKeys' => $rotation_keys,
                'verificationMethods' => $verification_methods,
                'alsoKnownAs' => $also_known_as,
                'services' => $services,
                'prev' => $prev,
                'handle' => $handle,
            ];

            $this->cid = 'bafy' . substr(hash('sha1', json_encode($this->data)), 0, 24);
        }

        public function get_cid(): string
        {
            return $this->cid;
        }

        public function jsonSerialize(): array
        {
            return $this->data;
        }

        public static function base64url_encode(string $value): string
        {
            return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        }
    }

    class PlcClient
    {
        public function create_did(string $did, array $operation): array
        {
            return ['did' => $did, 'operation' => $operation];
        }

        public function resolve_did(string $did): array
        {
            return [
                'id' => $did,
                'verificationMethod' => [
                    [
                        'id' => $did . '#atproto',
                        'publicKeyMultibase' => 'zStubPublicKey',
                    ],
                ],
                'alsoKnownAs' => [],
                'service' => [],
            ];
        }

        public function get_previous_cid(string $did): string
        {
            return 'bafyprevcidstub';
        }

        public function update_did(string $did, array $operation): void
        {
        }
    }
}

namespace FAIR\DID\Crypto {
    use FAIR\DID\Keys\EcKey;
    use FAIR\DID\Keys\EdDsaKey;
    use FAIR\DID\PLC\PlcOperation;

    class DidCodec
    {
        public static function generate_key_pair(): EcKey
        {
            return new EcKey('rotation-private-stub');
        }

        public static function generate_ed25519_key_pair(): EdDsaKey
        {
            return new EdDsaKey('verification-private-stub');
        }

        public static function create_plc_operation(EcKey $rotationKey, EdDsaKey $verificationKey, string $handle): PlcOperation
        {
            return new PlcOperation(handle: $handle);
        }

        public static function sign_plc_operation(PlcOperation $operation, EcKey $rotationKey): PlcOperation
        {
            return $operation;
        }

        public static function generate_plc_did(PlcOperation $signedOperation): string
        {
            return 'did:plc:' . substr(hash('sha256', json_encode($signedOperation->jsonSerialize())), 0, 24);
        }
    }
}

namespace FAIR\DID\Parsers {
    class PluginHeaderParser
    {
        public function parse_file(string $file): array
        {
            $content = (string) file_get_contents($file);
            return [
                'PluginName' => $this->extract($content, 'Plugin Name') ?? 'Unknown',
                'RequiresPHP' => $this->extract($content, 'Requires PHP') ?? '7.4',
                'RequiresAtLeast' => $this->extract($content, 'Requires at least') ?? '6.0',
            ];
        }

        private function extract(string $content, string $field): ?string
        {
            if (preg_match('/^\s*' . preg_quote($field, '/') . '\s*:\s*(.+)$/mi', $content, $matches) === 1) {
                return trim($matches[1]);
            }
            return null;
        }
    }

    class ReadmeParser
    {
        public function parse_file(string $file): array
        {
            return ['description' => trim((string) file_get_contents($file))];
        }
    }

    class MetadataGenerator
    {
        private ?string $did = null;

        public function __construct(private array $headerData, private array $readmeData)
        {
        }

        public function set_did(string $did): void
        {
            $this->did = $did;
        }

        public function generate(): array
        {
            return [
                'name' => $this->headerData['PluginName'] ?? 'Unknown',
                'description' => $this->readmeData['description'] ?? '',
                'id' => $this->did,
            ];
        }
    }
}
PHP;

    file_put_contents($autoloadPath, $stubCode);
}

fairPulseInstallDidManagerStubs();
