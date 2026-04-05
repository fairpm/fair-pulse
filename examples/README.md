# FAIR Pulse Examples

This folder contains copy-ready examples for integrating the FAIR Pulse action.

## Included

- `example.yml` - Single-job workflow that publishes FAIR metadata. FAIR Pulse handles artifact build and upload automatically.

## Apply in your repository

Copy `examples/example.yml` into your repository workflows folder, for example:

```bash
cp examples/example.yml .github/workflows/fair-publish.yml
```

## Setup

1. Run `composer fair:setup-local -- https://github.com/<owner>/<repo>` locally (one-time).
2. Add `FAIR_VERIFICATION_KEY_PRIVATE` and `FAIR_VERIFICATION_KEY_PUBLIC` as repository secrets.
3. Add `FAIR_DID` as a repository variable.
4. Back up rotation keys securely — they stay on your machine.
