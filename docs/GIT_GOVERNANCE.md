# Git Governance

**Status:** ENFORCED

## Branch Strategy
- master branch: Frozen documentation and architectural baseline. Contains no deployable application source code. History must not be rewritten without explicit Architecture Gate authorization.
- develop branch: The active, authoritative application source code. All feature development, bug fixes, and security remediations occur here.

## Development Rules
1. Future development must occur on the appropriate active development branch.
2. Production/source verification must establish the exact commit hash on develop that is deployed before a release is approved.
3. The master branch is strictly for preserving the architectural truth and must not be treated as a deployable artifact.
