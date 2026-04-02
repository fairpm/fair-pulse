# FAIR Pulse Examples

This folder contains copy-ready examples for integrating the FAIR Publish action.

## Included

- `.github/workflows/example.yml` - End-to-end example that creates a release ZIP and publishes FAIR metadata.

## Notes

- Generate keys locally first:
  - `composer install`
  - `composer fair:generate-keys-local`
- Add generated keys as repository secrets.
- Add `FAIR_DID` as a repository variable after first successful publish.
