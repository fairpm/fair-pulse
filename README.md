# FAIR Pulse GitHub Action

This action publishes a WordPress plugin release artifact to FAIR.

It handles:

1. Artifact signing
2. FAIR metadata generation
3. Metadata upload to GitHub release

## How It Works

FAIR Pulse uses two key pairs:

- **Rotation keys** (secp256k1) — control the DID identity. Used for DID creation and service endpoint updates.
- **Verification keys** (Ed25519) — sign release artifacts. Used during every publish.

The recommended setup creates the DID and sets its service endpoint locally (one-time), then CI only needs verification keys. Rotation keys never leave your machine.

The DID service endpoint is set to `https://github.com/<owner>/<repo>/releases/latest/download/fair-metadata.json` which GitHub automatically redirects to the most recent release. No DID updates are needed when publishing new releases.

## Before You Start

You need:

1. A GitHub repository with your plugin code
2. Permission to add repository secrets and variables
3. Local PHP + Composer
4. Git installed locally

## Step-By-Step Setup (Recommended)

### Step 1: Clone fair-pulse locally

```bash
mkdir -p ~/fair-tools
cd ~/fair-tools
git clone https://github.com/fairpm/fair-pulse.git
cd fair-pulse
```

### Step 2: Install dependencies

```bash
composer install
```

### Step 3: Run the local setup command

```bash
composer fair:setup-local -- https://github.com/your-name/your-plugin
```

This single command:
- Generates rotation and verification key pairs on your machine
- Creates a DID on the PLC directory
- Sets the DID service endpoint to the static latest-release URL
- Prints instructions for what to add to GitHub

### Step 4: Add verification keys and DID to GitHub

From the setup output, add these to your plugin repository:

**Secrets** (Settings → Secrets and variables → Actions → Secrets tab):
1. `FAIR_VERIFICATION_KEY_PRIVATE`
2. `FAIR_VERIFICATION_KEY_PUBLIC`

**Variable** (Settings → Secrets and variables → Actions → Variables tab):
1. `FAIR_DID`

### Step 5: Back up rotation keys securely

The setup command also prints rotation keys. **Do NOT add them to GitHub.**

Store them in a password manager or encrypted backup. You only need them if you ever change the DID document (which is rare).

### Step 6: Add workflow file to your plugin repository

Create `.github/workflows/publish-fair.yml`:

```yaml
name: Publish FAIR Metadata

on:
  push:
    tags: ["v*"]
  workflow_dispatch:
    inputs:
      version:
        description: Version tag (for example v1.2.3)
        required: false
        type: string

permissions:
  contents: write

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Publish to FAIR
        uses: fairpm/fair-pulse@v1
        with:
          version: ${{ inputs.version }}
          artifact-name: my-plugin.zip
          upload-metadata: 'true'
        env:
          FAIR_VERIFICATION_KEY_PRIVATE: ${{ secrets.FAIR_VERIFICATION_KEY_PRIVATE }}
          FAIR_VERIFICATION_KEY_PUBLIC: ${{ secrets.FAIR_VERIFICATION_KEY_PUBLIC }}
          FAIR_DID: ${{ vars.FAIR_DID }}
```

### Step 7: Push a tag or run the workflow

Tag and push a release, or trigger the workflow manually from the Actions tab.

FAIR Pulse handles building the artifact, creating the release, signing, metadata generation, and uploading — all automatically.

## Inputs

| Input | Required | Default | Description |
|---|---|---|---|
| version | No | empty | Version/tag to publish. If empty, resolves from tag ref or latest git tag. |
| artifact-name | No | empty | Release asset filename to download and sign. Must end in `.zip`. |
| upload-metadata | No | true | Upload generated `fair-metadata.json` to release. |

## Outputs

| Output | Description |
|---|---|
| version | Published release version |
| did | DID used during publish |
| artifact-path | Local path of downloaded artifact |
| metadata-path | Local path of generated FAIR metadata |

## Updating the DID Service Endpoint

`fair:setup-local` sets the service endpoint once during initial setup. Because the endpoint uses GitHub's `/releases/latest/download/` URL, it never needs updating when you publish new releases.

If you ever need to point the endpoint to a different URL (for example, after migrating repositories), run this locally using your rotation private key:

```bash
composer fair:update-did-service
```

Running it without arguments will prompt for each required value interactively:

```
Your DID (e.g. did:plc:abc123): did:plc:yourdid
Rotation private key: <paste key>
Metadata URL: https://github.com/your-name/your-plugin/releases/latest/download/fair-metadata.json
Previous operation CID (optional, press Enter to skip):
```

Alternatively, pass values as environment variables to skip prompting:

```bash
DID=did:plc:yourdid \
ROTATION_PRIVATE=your_rotation_private_key \
METADATA_URL=https://github.com/your-name/your-plugin/releases/latest/download/fair-metadata.json \
composer fair:update-did-service
```

`PREV_CID` is optional. If you have the CID from the last DID operation, pass it to ensure consistency; otherwise it is fetched automatically from the PLC directory.

## Example Files In This Repository

1. Full workflow example: `examples/example.yml`
2. Example guide: `examples/README.md`

## Local Execution (Optional)

Build Docker image:

```bash
docker build -t fair-pulse-local .
```

Run local setup in Docker:

```bash
docker run --rm -it fair-pulse-local composer fair:setup-local -- https://github.com/owner/repo
```

Run full publish flow in Docker:

```bash
docker run --rm -it \
  -e GITHUB_REPOSITORY=owner/repo \
  -e GITHUB_SERVER_URL=https://github.com \
  -e GITHUB_TOKEN=ghp_xxx \
  -e INPUT_VERSION=v1.2.3 \
  -e INPUT_ARTIFACT_NAME=my-plugin.zip \
  -e FAIR_VERIFICATION_KEY_PRIVATE=... \
  -e FAIR_VERIFICATION_KEY_PUBLIC=... \
  -e FAIR_DID=did:plc:yourDid \
  fair-pulse-local
```

## Troubleshooting

1. Missing verification keys:
Set `FAIR_VERIFICATION_KEY_PRIVATE` and `FAIR_VERIFICATION_KEY_PUBLIC` as repository secrets.

2. Missing DID and no rotation keys:
Run `composer fair:setup-local -- https://github.com/<owner>/<repo>` locally first.

3. Artifact not found:
Ensure `artifact-name` matches the exact release asset filename (or omit it to auto-detect).

4. Permission issues:
Workflow needs `contents: write` permission to upload metadata.

## Development

```bash
vendor/bin/phpunit
composer validate --no-check-publish
```

## Changelog

See `CHANGELOG.md`.

