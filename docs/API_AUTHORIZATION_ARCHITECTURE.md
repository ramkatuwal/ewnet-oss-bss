# API Authorization Architecture (TASK-016)

**Status:** PASS

## Original Findings & Remediation
1. Finding: UserPolicy lacked organizational boundary checks.
   Remediation: Implemented userCanManageTarget() enforcing strict hierarchical checks.
2. Finding: UserController::index used unrestricted User::get().
   Remediation: Replaced with authorization-aware scoped queries based on the authenticated user's organizational level.
3. Finding: UserController::store lacked parent-ID validation.
   Remediation: Created UserRequest with hierarchical validation, deriving ownership from the authenticated actor and rejecting out-of-scope IDs.

## API Security Lifecycle
Request -> Sanctum Middleware -> Controller Authorization -> Policy Evaluation -> Query Scope -> Form Request Validation -> Mutation -> Audit Event
