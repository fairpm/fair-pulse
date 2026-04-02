# FAIR Publish GitHub Action

This action publishes a WordPress plugin release artifact to FAIR.

It handles:

1. DID creation or reuse
2. Artifact signing
3. FAIR metadata generation
4. Metadata upload to GitHub release
5. DID service endpoint update

## Before You Start

You need:

1. A GitHub repository with your plugin code
2. A release ZIP asset (for example `my-plugin.zip`) attached to a GitHub release
3. Permission to add repository secrets and variables
4. Local PHP + Composer to generate keys
5. Git installed locally

## Step-By-Step Setup

### Step 1: Create a local working folder and clone this repo

If you already cloned `fair-pulse`, skip to Step 2.

```bash
mkdir -p ~/fair-tools
cd ~/fair-tools
git clone https://github.com/fairpm/fair-pulse.git
cd fair-pulse
```

At this point, your terminal should be inside the `fair-pulse` folder.

### Step 2: Install dependencies in that folder

Run this from inside `fair-pulse`:

```bash
composer install
```

### Step 3: Generate keys locally (on your machine only)

Still in the same `fair-pulse` folder, run:

```bash
composer fair:generate-keys-local
```

Alternative command:

```bash
php src/actions/GenerateKeysLocalAction.php
```

This prints four values. Keep that terminal open until you finish Step 4.

### Step 4: Add keys to GitHub Secrets

Add these values as repository secrets:

1. FAIR_ROTATION_KEY_PRIVATE
2. FAIR_ROTATION_KEY_PUBLIC
3. FAIR_VERIFICATION_KEY_PRIVATE
4. FAIR_VERIFICATION_KEY_PUBLIC

In GitHub UI:

1. Open your plugin repository
2. Go to Settings
3. Go to Secrets and variables -> Actions
4. Open Secrets tab
5. Click New repository secret
6. Add each key/value from Step 3

### Step 5: Add workflow file to your plugin repository

In your plugin repository (not in `fair-pulse`), create:

1. `.github/workflows/publish-fair.yml`

If the folders do not exist, create them first:

```bash
mkdir -p .github/workflows
```

Then paste this workflow:

```yaml
name: Publish FAIR Metadata

on:
  workflow_dispatch:
    inputs:
      version:
        description: Version tag (for example v1.2.3)
        required: true
        type: string

permissions:
  contents: write

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

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

Commit and push that file to your plugin repository.

### Step 6: Make sure the release ZIP name matches

In the workflow above, `artifact-name` is set to `my-plugin.zip`.

That must exactly match the release asset filename attached to your GitHub release.

### Step 7: Run the workflow for your first publish

1. Go to Actions in your plugin repository
2. Open Publish FAIR Metadata
3. Click Run workflow
4. Enter your version (for example `v1.2.3`)
5. Run it

### Step 8: Save the DID after first successful run

If `FAIR_DID` is not set yet, the action creates one.

Add it as a repository variable:

1. Name: FAIR_DID
2. Value: the generated `did:plc:...` from logs/outputs

In GitHub UI:

1. Settings -> Secrets and variables -> Actions
2. Open Variables tab
3. Click New repository variable
4. Add `FAIR_DID`

Future runs reuse this DID.

## Minimal Workflow Snippet (Reference)

```yaml
name: Publish FAIR Metadata

on:
  workflow_dispatch:
    inputs:
      version:
        description: Version tag (for example v1.2.3)
        required: true
        type: string

permissions:
  contents: write

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

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
| version | No | empty | Version/tag to publish. If empty, resolves from tag ref or latest git tag. |
| artifact-name | No | empty | Release asset filename to download and sign. Must end in `.zip`. |
| upload-metadata | No | true | Upload generated `fair-metadata.json` to release. |
| update-did-service | No | true | Update DID service endpoint to uploaded metadata URL. |

## Outputs

| Output | Description |
|---|---|
| version | Published release version |
| did | DID used during publish |
| artifact-path | Local path of downloaded artifact |
| metadata-path | Local path of generated FAIR metadata |

## Example Files In This Repository

1. Full workflow example: `examples/example.yml`
2. Example guide: `examples/README.md`

## Local Execution (Optional)

Build Docker image:

```bash
docker build -t fair-pulse-local .
```

Run local key generation in Docker:

```bash
docker run --rm -it fair-pulse-local php src/actions/GenerateKeysLocalAction.php
```

Run full publish flow in Docker:

```bash
docker run --rm -it \
  -e GITHUB_REPOSITORY=owner/repo \
  -e GITHUB_SERVER_URL=https://github.com \
  -e GITHUB_TOKEN=ghp_xxx \
  -e INPUT_VERSION=v1.2.3 \
  -e INPUT_ARTIFACT_NAME=my-plugin.zip \
  -e FAIR_ROTATION_KEY_PRIVATE=... \
  -e FAIR_ROTATION_KEY_PUBLIC=... \
  -e FAIR_VERIFICATION_KEY_PRIVATE=... \
  -e FAIR_VERIFICATION_KEY_PUBLIC=... \
  -e FAIR_DID=did:plc:optionalExistingDid \
  fair-pulse-local
```

## Troubleshooting

1. Missing keys error:
Generate keys locally and add all four key secrets.

2. Artifact not found:
Ensure `artifact-name` matches the exact release asset filename.

3. Permission issues:
Workflow needs `contents: write` permission to upload metadata.

## Development

```bash
vendor/bin/phpunit
composer validate --no-check-publish
```

## Changelog

See `CHANGELOG.md`.

