# FAIR Publish GitHub Action

Publish WordPress release artifacts to FAIR using a reusable GitHub Action with DID management, signature generation, and FAIR metadata upload.

## What this action does

1. Validates required inputs and environment.
2. Resolves version/tag to publish.
3. Ensures FAIR DID manager dependency is installed.
4. Loads keys from secrets and uses existing DID if available.
5. Downloads release ZIP asset.
6. Signs artifact and builds FAIR metadata.
7. Uploads fair-metadata.json to release.
8. Updates DID service endpoint to FAIR metadata URL.

## Marketplace usage

```yaml
name: FAIR Publish

on:
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
          update-did-service: 'true'
        env:
          FAIR_ROTATION_KEY_PRIVATE: ${{ secrets.FAIR_ROTATION_KEY_PRIVATE }}
          FAIR_ROTATION_KEY_PUBLIC: ${{ secrets.FAIR_ROTATION_KEY_PUBLIC }}
          FAIR_VERIFICATION_KEY_PRIVATE: ${{ secrets.FAIR_VERIFICATION_KEY_PRIVATE }}
          FAIR_VERIFICATION_KEY_PUBLIC: ${{ secrets.FAIR_VERIFICATION_KEY_PUBLIC }}
          FAIR_DID: ${{ vars.FAIR_DID }}
```

## Inputs

| Input | Required | Default | Description |
|---|---|---|---|
| version | No | empty | Version/tag to publish. If empty, action resolves from tag ref or latest git tag. |
| artifact-name | No | empty | Release asset filename to download and sign. Must end in .zip. |
| upload-metadata | No | true | Upload generated fair-metadata.json to release. |
| update-did-service | No | true | Update DID service endpoint to uploaded metadata URL. |

## Outputs

| Output | Description |
|---|---|
| version | Published release version. |
| did | DID used during publish. |
| artifact-path | Local path of downloaded artifact. |
| metadata-path | Local path of generated FAIR metadata file. |

## Required secrets and variables

Secrets:

- FAIR_ROTATION_KEY_PRIVATE
- FAIR_ROTATION_KEY_PUBLIC
- FAIR_VERIFICATION_KEY_PRIVATE
- FAIR_VERIFICATION_KEY_PUBLIC

Variable:

- FAIR_DID (optional on first run, required for stable DID reuse)

## Generate keys locally

Run locally (never in CI):

```bash
composer install
composer fair:generate-keys-local
```

Alternative direct command:

```bash
php src/actions/GenerateKeysLocalAction.php
```

Copy output values into GitHub repository secrets.

## DID manager dependency

This action now uses Composer packages instead of cloning a git repo at runtime:

- fairpm/did-manager-wordpress
- fairpm/did-manager (transitive dependency)

The composite action runs composer install automatically before publish execution.

## Validation and error handling

The action validates:

- version format (semver-style tag)
- artifact-name pattern (.zip required)
- boolean inputs (true or false)
- required GitHub environment (repository, token, server URL)
- required FAIR key presence

If any check fails, the action exits non-zero with clear error logs.

## Structured logging

The action writes grouped GitHub logs for:

- dependency installation
- key loading
- DID creation/update
- artifact download/signing
- metadata generation/upload

## Local entrypoints

Main entrypoint used by action.yml:

- src/actions/PublishFairAction.php

Supporting action entrypoints:

- src/actions/ManageKeysAction.php
- src/actions/CreateDidAction.php
- src/actions/SignArtifactAction.php
- src/actions/GenerateMetadataAction.php
- src/actions/UpdateDidServiceAction.php

## Local execution (Docker)

Build local image:

```bash
docker build -t fair-pulse-local .
```

Generate key pairs locally in Docker:

```bash
docker run --rm -it fair-pulse-local php src/actions/GenerateKeysLocalAction.php
```

Run full publish flow locally in Docker:

```bash
docker run --rm -it \
  -e GITHUB_REPOSITORY=owner/repo \
  -e GITHUB_SERVER_URL=https://github.com \
  -e GITHUB_TOKEN=ghp_xxx \
  -e INPUT_VERSION=v1.2.3 \
  -e INPUT_ARTIFACT_NAME=plugin.zip \
  -e FAIR_ROTATION_KEY_PRIVATE=... \
  -e FAIR_ROTATION_KEY_PUBLIC=... \
  -e FAIR_VERIFICATION_KEY_PRIVATE=... \
  -e FAIR_VERIFICATION_KEY_PUBLIC=... \
  -e FAIR_DID=did:plc:optionalExistingDid \
  fair-pulse-local
```

Notes:

- Key generation must still be done locally (never in GitHub-hosted workflow runtime).
- For publish flow, GITHUB_TOKEN needs permission to read/upload release assets.
- INPUT_ARTIFACT_NAME must match the release asset name in GitHub.

## Development check

```bash
vendor/bin/phpunit
```

