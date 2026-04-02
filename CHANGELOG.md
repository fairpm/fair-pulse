# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added

- Marketplace-ready action manifest with inputs, outputs, and branding.
- Central publish orchestration action with validation and structured logs.
- Unit, integration, and basic e2e test coverage for action flows.
- Docker-based local execution pathway and example workflows.
- CI workflow with lint, test, and build-validation gates.

### Changed

- Migrated from procedural script layout to OOP structure under src.
- Switched DID manager usage to Composer package dependencies.
- Improved README with quick start and clear local execution guidance.

### Removed

- Legacy script compatibility layer and runtime did-manager git clone path.

## [0.1.0] - 2026-04-03

### Added

- Initial production-ready FAIR Pulse GitHub Action release.
- DID creation/update, artifact signing, FAIR metadata generation, and release upload flow.