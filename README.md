# AMC Architecture Showcase

Selected engineering work from AMC, a larger private business operations platform.

This is a portfolio repository, not the AMC production source tree. It contains selected implementation examples that demonstrate backend architecture, validation, workflow integrity, and separation of concerns while keeping the commercial platform private.

## Featured Subsystem: Site Intake

Site Intake processes structured onboarding information through a larger workflow:

Input → Normalization → Validation → Review → Approval → Export

This public showcase exposes only a narrow portion of that system.

## Included Examples

### Package Validation
`site-intake/validation/SiteIntakeValidator.php`

Demonstrates contract validation, structured errors, safe file paths, SHA-256 verification, MIME allowlisting, package limits, duplicate-path detection, unsafe URL rejection, and secret-field detection.

### Approval Invalidation
`site-intake/services/SiteIntakeApprovalInvalidator.php`

Demonstrates how previously approved/exported state is invalidated when authoritative upstream data changes, preventing stale approved information from remaining authoritative.

### Explicit Data Boundaries
`site-intake/data/`

Small data objects maintain clear boundaries between stages of the workflow.

## Architecture

See `docs/architecture.md` for additional design notes.

## Technology Context

PHP · Laravel · MySQL · Vue · Inertia · Git · Automated Testing

## Production Context

AMC is a much larger private application containing additional business workflows, integrations, frontend systems, communications infrastructure, analytics, lead-management systems, commercial logic, and deployment architecture.

Those systems are intentionally not reproduced here.

This repository exists so recruiters and engineering teams can evaluate real implementation decisions without exposing the private commercial application.

## Note

These are selected production excerpts. Some dependencies remain inside the private AMC codebase, so this repository is intended for technical review rather than standalone deployment.

No license is granted for reuse of the source code contained in this repository.
