# FAIR Pulse Examples

This folder contains copy-ready examples for integrating the FAIR Pulse action.

## Included

- `example.yml` - End-to-end example that creates a release ZIP and publishes FAIR metadata.

## Apply in your repository

Copy `examples/example.yml` into your repository workflows folder, for example:

```bash
cp examples/example.yml .github/workflows/fair-publish.yml
```

## Notes

- Generate keys locally first:
  - `composer install`
  - `composer fair:generate-keys-local`
- Add generated keys as repository secrets.
- Add `FAIR_DID` as a repository variable after first successful publish.
