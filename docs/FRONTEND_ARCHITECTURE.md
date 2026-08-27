# Frontend Management Architecture — TASK-019 Forensic Audit & Rebuild Plan

**Date:** August 27, 2026  
**Project:** EWNET OSS/BSS (MISP)  
**Phase:** Phase 3 — Frontend Management Rebuild  
**Status:** FORENSIC AUDIT COMPLETE (No Implementation Performed)  
**Frozen Baseline:** `phase-2-frozen-20260827` (Commit: `1e7a3aa`)

---

## 1. Current Frontend Architecture

### Technology Stack (Verified)
| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | React | 18.3.1 |
| Language | TypeScript | Strict mode |
| UI Library | MUI (Material UI) | 6.1.1 |
| Routing | React Router DOM | 6.26.1 |
| Server State | TanStack Query | 5.55.4 |
| Client State | Zustand | 4.5.5 |
| Forms | React Hook Form + Zod | 7.53.0 / 3.23.8 |
| HTTP Client | Axios | 1.7.7 |
| Build Tool | Vite + Laravel Plugin | - |
| Icons | MUI Icons | 6.1.1 |
| Maps | Leaflet + React-Leaflet | 1.9.4 / 4.2.1 |

### Directory Structure

resources/js/
├── api/ # Axios API clients (auth, companies, regions, branches, etc.)
├── app/ # App.tsx root component
├── components/ # Shared components (ProtectedRoute, DataTable)
│ ├── forms/ # (empty)
│ ├── layout/ # ProtectedRoute
│ ├── map/ # (empty)
│ ├── navigation/ # (empty)
│ └── tables/ # DataTable
├── constants/ # (empty)
├── css/ # Global styles
├── features/ # Feature-based page modules
│ ├── auth/pages/ # LoginPage
│ ├── branches/pages/
│ ├── companies/pages/
│ ├── dashboard/pages/
│ ├── debug/pages/
│ ├── departments/pages/
│ ├── permissions/pages/
│ ├── regions/pages/
│ ├── roles/pages/
│ └── users/pages/
├── hooks/ # (empty)
├── layouts/ # MainLayout, AuthLayout
├── providers/ # (empty)
├── routes/ # AppRouter
├── schemas/ # (empty)
├── stores/ # authStore, themeStore
├── theme/ # MUI theme config
├── types/ # TypeScript type definitions
└── utils/ # (empty)



### Bundle Size
- **Current:** 576 KB (public/build/)
- **Warning:** Chunks > 500 KB detected; code-splitting recommended

---

## 2. Current Navigation

### Existing Menu Structure (Flat List)

Dashboard
Companies ← TOP LEVEL (should be under Manage)
Regions ← TOP LEVEL (should be under Manage)
Branches ← TOP LEVEL (should be under Manage)
Departments ← TOP LEVEL (should be under Manage)
Users ← TOP LEVEL (should be under Manage)
Roles ← TOP LEVEL (should be under Manage)
Permissions ← TOP LEVEL (should be under Manage)
Debug ← TOP LEVEL


### Problems Identified
- ❌ No hierarchical grouping — all items are flat top-level entries
- ❌ No "Manage" parent section
- ❌ No collapsible/expandable sections
- ❌ No permission-aware visibility filtering
- ❌ Departments listed but should be nested under Branches contextually
- ❌ FIM placeholder comments exist (`// FIM Icons`, `// FIM Modules`)
- ❌ Debug page exposed in production navigation
- ❌ No active-parent indication for grouped items
- ❌ No Account/Profile section in navigation

---

## 3. Manage Menu Findings

**Current State:** The "Manage" concept does not exist. All administrative entities are flat top-level navigation items.

**Required State:**


Manage
├── Companies
├── Regions
├── Branches
├── Users
├── Roles
└── Permissions


**Key UX Requirements:**
- Expandable/collapsible Manage section in sidebar
- Active child route highlights both child AND parent
- Permission-based visibility (hide items user cannot access)
- Breadcrumb trail: `Manage > Companies > Edit`
- Mobile: Manage section expands via accordion pattern

---

## 4. Current Routes

| Path | Component | Auth Required | Should Retain? | Notes |
|------|-----------|:---:|:---:|-------|
| `/login` | LoginPage | No | ✅ | Keep as-is |
| `/` | Redirect → /dashboard | Yes | ✅ | Keep |
| `/dashboard` | DashboardPage | Yes | ✅ | Rebuild content |
| `/companies` | CompaniesPage | Yes | ⚠️ | Move to `/manage/companies` |
| `/regions` | RegionsPage | Yes | ⚠️ | Move to `/manage/regions` |
| `/branches` | BranchesPage | Yes | ⚠️ | Move to `/manage/branches` |
| `/departments` | DepartmentsPage | Yes | ⚠️ | Move to `/manage/departments` or remove |
| `/users` | UsersPage | Yes | ⚠️ | Move to `/manage/users` |
| `/roles` | RolesPage | Yes | ⚠️ | Move to `/manage/roles` |
| `/permissions` | PermissionsPage | Yes | ⚠️ | Move to `/manage/permissions` |
| `/debug` | DebugPage | Yes | ⚠️ | Remove from nav; keep route for dev only |

**Missing Routes:**
- `/audit/security` — Security Activity log viewer
- `/account/profile` — User profile management
- `/manage/*` — Nested route prefix

---

## 5. Company UI Findings

**Current:** Single `CompaniesPage.tsx` — likely a basic CRUD table.

**Gaps Identified:**
- No dedicated detail/view page
- No organizational context display
- No activate/deactivate toggle visible in audit
- No search/filter bar confirmed
- No empty state handling confirmed
- No permission-aware action buttons confirmed

**Target Page Structure:**

/manage/companies
├── Page Header (title + Create button)
├── Search + Filter Bar
├── Data Table (name, status, region count, branch count, actions)
├── Pagination
└── Create/Edit Drawer or Modal
/manage/companies/:id
├── Overview Card
├── Regions belonging to this company
├── Status & Metadata
├── Audit Activity Tab
└── Edit / Deactivate Actions


---

## 6. Region UI Findings

**Current:** Single `RegionsPage.tsx`.

**Gaps:**
- Parent Company context not clearly displayed
- No Company filter dropdown
- Hierarchy breadcrumb missing
- No visual indication of Company → Region relationship

**Target:** Must clearly show parent Company in list and detail views. Filter by Company required.

---

## 7. Branch UI Findings

**Current:** Single `BranchesPage.tsx`.

**Gaps:**
- Parent Region context not clearly displayed
- Company context missing
- No hierarchical navigation (Company → Region → Branch)
- No Region filter

**Target:** Must communicate `Company → Region → Branch` hierarchy visually.

---

## 8. User UI Findings (CRITICAL SECURITY AREA)

**Current:** Single `UsersPage.tsx`.

**Critical Gaps:**
- Organization scope filtering not confirmed in UI
- Role assignment UI may allow arbitrary role selection
- No visual indication of organizational boundaries
- Department field present but Departments module is retired from active business logic
- No self-escalation prevention feedback in UI

**Security Requirements for Rebuild:**
- Organization selector must ONLY show options within authenticated user's scope
- Role selector must ONLY show roles the actor can assign
- Super Admin badge must be visually distinct and protected
- Backend remains authoritative; UI is convenience only
- 403 responses must display clear unauthorized messaging

---

## 9. Role UI Findings

**Current:** Single `RolesPage.tsx`.

**Gaps:**
- Super Admin protection not visible in UI
- Permission assignment interface unclear
- No assigned-users view
- No visual distinction for system-protected roles

**Target:**
- Super Admin role visually marked as protected
- Permission matrix with checkboxes grouped by domain
- Assigned users list/tab
- Delete/rename disabled for protected roles

---

## 10. Permission UI Findings

**Current:** Single `PermissionsPage.tsx`.

**Gaps:**
- No grouping by domain (organization, security, users, etc.)
- No search/filter
- Create/edit/delete available but should be Super Admin only
- No role association view

**Target:** Read-only for non-Super Admin. Grouped display. Role association visible.

---

## 11. Authentication UI Findings

**Current:** `LoginPage.tsx` + `AuthLayout.tsx`

**Strengths:**
- CSRF cookie fetched before login ✅
- Session-based Sanctum auth ✅
- Auth store handles booting/authenticated/anonymous states ✅
- Logout clears state ✅

**Gaps:**
- No session expiration handling / auto-refresh
- No 401 interceptor redirect confirmed in client
- No loading skeleton during auth hydration
- No "remember me" option
- No password reset flow

---

## 12. API Client Findings

**Current:** Axios instance with CSRF interceptor and 401 handler stub.

**Strengths:**
- `withCredentials: true` for Sanctum cookie auth ✅
- CSRF token injection ✅
- Consistent base URL via env var ✅
- Paginated response typing ✅

**Gaps:**
- 401 handler is a stub (no redirect implemented)
- No 403 error handling
- No 422 validation error normalization
- No retry logic
- No request deduplication (TanStack Query handles this at query level)
- `PaginatedResponse` type duplicated across API modules instead of shared

---

## 13. State Management Findings

### Current Architecture
| Store | Purpose | Assessment |
|-------|---------|------------|
| `authStore` (Zustand) | User session, permissions, login/logout | ✅ Well-structured |
| `themeStore` (Zustand + persist) | Light/dark mode | ✅ Appropriate |
| TanStack Query | Server state (not yet integrated into pages) | ⚠️ Installed but unused |

### Recommended Architecture
| Concern | Solution |
|---------|----------|
| Auth session | Zustand (keep current) |
| Theme/UI preferences | Zustand + persist (keep current) |
| Entity lists/details | TanStack Query (implement in rebuild) |
| Form state | React Hook Form (local, keep current) |
| Filter/pagination state | URL search params + TanStack Query |
| Modal/drawer open state | Local React state |
| Toast notifications | Lightweight toast library or MUI Snackbar |

---

## 14. Authorization-Aware UI Findings

**Current:** `authStore.hasPermission()` exists and supports Super Admin bypass.

**Gaps:**
- Navigation menu does NOT filter by permissions
- Page-level permission checks not implemented
- Action buttons (Create, Edit, Delete) not conditionally rendered
- No `<Can permission="...">` wrapper component exists
- 403 error responses not handled gracefully in UI

**Required Components:**
```tsx
<Can permission="companies.create">
  <Button>Create Company</Button>
</Can>

15. Responsive / UI / UX Findings
Current:
MUI Drawer with temporary (mobile) + permanent (desktop) variants ✅
AppBar with hamburger menu for mobile ✅
Avatar menu for user actions ✅
Gaps:
Data tables not responsive (no horizontal scroll or card layout for mobile)
Forms not tested for tablet/mobile
No skeleton loading states
No confirmation dialogs for destructive actions
No toast/notification system
No breadcrumbs
Typography hierarchy inconsistent
Spacing/padding not standardized

16. Legacy UI Findings
File
Reference
Classification
Action
DashboardPage.tsx:9
'Total Employees' stat card
A. Active UI Code
REMOVE in rebuild
MainLayout.tsx:33
// FIM Icons comment
B. Historical Comment
REMOVE
MainLayout.tsx:52
// FIM Modules comment
B. Historical Comment
REMOVE
No active legacy module routes, pages, or API calls found. ✅
17. Performance Findings
Metric
Current
Target
Bundle size
576 KB
< 400 KB initial
Code splitting
None
Route-level lazy loading
Leaflet bundle
Included globally
Lazy-load only if maps needed
TanStack Query
Installed, unused
Implement for all entity queries
Image optimization
N/A
No images currently
Unused dependencies
react-leaflet, leaflet
Remove unless maps planned
Recommendation: Remove Leaflet dependencies unless FIM/maps are planned for Phase 3. Implement React.lazy() + Suspense for all feature pages.
18. Proposed Navigation

EWNET OSS/BSS
│
├── 📊 Dashboard
│
├── 📁 Manage                          ← Expandable Section
│   ├── 🏢 Companies                   ← /manage/companies
│   ├── 🗺️ Regions                     ← /manage/regions
│   ├── 🏪 Branches                    ← /manage/branches
│   ├── 👥 Users                       ← /manage/users
│   ├── 🛡️ Roles                       ← /manage/roles
│   └── 🔑 Permissions                 ← /manage/permissions
│
├── 📋 Audit                           ← Expandable Section
│   └── 🔍 Security Activity           ← /audit/security
│
└── 👤 Account                         ← Avatar Menu
    ├── Profile                        ← /account/profile
    └── Logout
	
	Permission-Based Visibility:
Manage section visible if user has ANY manage permission
Individual items hidden if user lacks corresponding .view permission
Audit section visible only to Super Admin or users with audit.view
Account always visible
19. Proposed Frontend Architecture

resources/js/
├── api/                    # API clients (refactored, shared types)
├── app/                    # Root App component
├── components/
│   ├── auth/               # Can, PermissionGuard
│   ├── feedback/           # Toast, AlertBanner, ErrorBoundary
│   ├── forms/              # FormField, Select, SearchInput
│   ├── layout/             # ProtectedRoute, Breadcrumbs, PageHeader
│   ├── navigation/         # Sidebar, NavGroup, NavItem, UserMenu
│   ├── tables/             # DataTable, TablePagination, EmptyState
│   └── ui/                 # Button, Dialog, Drawer, Badge, Skeleton
├── features/
│   ├── auth/               # Login, session management
│   ├── dashboard/          # Dashboard widgets
│   ├── manage/
│   │   ├── companies/      # List, Detail, Create/Edit
│   │   ├── regions/        # List, Detail, Create/Edit
│   │   ├── branches/       # List, Detail, Create/Edit
│   │   ├── users/          # List, Detail, Create/Edit, RoleAssignment
│   │   ├── roles/          # List, Detail, Create/Edit, PermissionMatrix
│   │   └── permissions/    # List (read-only for non-admin)
│   ├── audit/              # SecurityActivity log viewer
│   └── account/            # Profile
├── hooks/                  # useCan, useOrganizationScope, usePagination
├── layouts/                # MainLayout (rebuilt), AuthLayout
├── routes/                 # AppRouter with lazy loading
├── stores/                 # authStore, themeStore (kept)
├── theme/                  # MUI theme (refined)
├── types/                  # Shared TypeScript interfaces
└── utils/                  # formatDateTime, cn(), errorParser

20. Proposed Component Architecture
Reusable Components
Component
Purpose
<Can permission>
Conditional render based on permission
<PageHeader>
Title + breadcrumbs + primary action
<DataTable>
Sortable, paginated, responsive table
<SearchFilter>
Debounced search + filter dropdowns
<FormDrawer>
Slide-out drawer for create/edit forms
<ConfirmDialog>
Destructive action confirmation
<Toast>
Success/error notification
<Skeleton>
Loading placeholder
<EmptyState>
No-data illustration + CTA
<StatusBadge>
Active/inactive/suspended indicator
<OrgBreadcrumb>
Company → Region → Branch hierarchy display
21. Proposed Routing Architecture


<Routes>
  {/* Public */}
  <Route element={<AuthLayout />}>
    <Route path="/login" element={<LoginPage />} />
  </Route>

  {/* Protected */}
  <Route element={<ProtectedRoute />}>
    <Route element={<MainLayout />}>
      <Route index element={<Navigate to="/dashboard" replace />} />
      <Route path="dashboard" element={<LazyDashboard />} />

      {/* Manage Section */}
      <Route path="manage">
        <Route path="companies" element={<LazyCompaniesList />} />
        <Route path="companies/:id" element={<LazyCompanyDetail />} />
        <Route path="regions" element={<LazyRegionsList />} />
        <Route path="regions/:id" element={<LazyRegionDetail />} />
        <Route path="branches" element={<LazyBranchesList />} />
        <Route path="branches/:id" element={<LazyBranchDetail />} />
        <Route path="users" element={<LazyUsersList />} />
        <Route path="users/:id" element={<LazyUserDetail />} />
        <Route path="roles" element={<LazyRolesList />} />
        <Route path="roles/:id" element={<LazyRoleDetail />} />
        <Route path="permissions" element={<LazyPermissionsList />} />
      </Route>

      {/* Audit Section */}
      <Route path="audit">
        <Route path="security" element={<LazySecurityActivity />} />
      </Route>

      {/* Account */}
      <Route path="account">
        <Route path="profile" element={<LazyProfile />} />
      </Route>
    </Route>
  </Route>
</Routes>

All feature pages loaded via React.lazy() + Suspense.
22. Proposed State Architecture
State Type
Solution
Example
Auth session
Zustand authStore
user, permissions, login/logout
Theme
Zustand themeStore + persist
light/dark mode
Entity lists
TanStack Query useQuery
companies, users, roles
Entity mutations
TanStack Query useMutation
create, update, delete
Form data
React Hook Form
local form state
Filters/pagination
URL search params
?page=2&search=abc&company=1
UI state (modals)
Local useState
drawer open, dialog open
Notifications
Toast context/store
success/error messages
23. Proposed API Integration Architecture
Refinements Needed
Shared PaginatedResponse<T> type — move to types/index.ts
401 interceptor — redirect to /login on session expiry
403 handler — display unauthorized toast/banner
422 handler — normalize Laravel validation errors for React Hook Form
Query keys factory — centralized query key management
Optimistic updates — for toggle/status changes
Cache invalidation — auto-invalidate related queries after mutations
24. Proposed Rebuild Task Sequence
Task
Name
Scope
Dependencies
TASK-020
Frontend Shell & Navigation
MainLayout rebuild, Manage menu, routing, lazy loading, breadcrumbs, <Can> component
None
TASK-021
Company Management UI
List, detail, create/edit drawer, search/filter, status badges
TASK-020
TASK-022
Region & Branch Management UI
List, detail, create/edit, parent hierarchy display, filtering
TASK-021
TASK-023
User Management UI
List, detail, create/edit, org-scoped selectors, role assignment, self-escalation prevention
TASK-020
TASK-024
Role & Permission Management UI
Role CRUD, permission matrix, Super Admin protection, assigned users view
TASK-020
TASK-025
Audit & Account UI
Security activity log viewer, profile page, toast/notification system
TASK-020
TASK-026
Frontend Security & UX Verification
Full regression, authorization testing, responsive testing, performance audit
TASK-021–025
TASK-027
Frontend Final Gate
Production build, accessibility audit, documentation update
TASK-026
25. Documentation Created/Updated
File
Action
Content
docs/FRONTEND_ARCHITECTURE.md
CREATED
This document
No other files were modified.
26. Git Status

Branch: develop
HEAD: 1e7a3aa (phase-2-frozen-20260827)
Untracked: docs/FRONTEND_ARCHITECTURE.md (this document)
Modified: NONE
Deleted: NONE
Master: e955159 (untouched)

27. Confirmations
✅ Phase 2 remains frozen — tag phase-2-frozen-20260827 intact
✅ Backend security architecture unchanged — no PHP/routes/policies modified
✅ Database unchanged — no migrations or schema changes
✅ API behavior unchanged — no controller or route modifications
✅ Authentication unchanged — Sanctum/session auth preserved
✅ RBAC unchanged — no role/permission modifications
✅ Policies unchanged — all 7 policies intact
✅ FIM not started — no FIM code, routes, or models created
✅ TASK-019 implementation not started — audit and documentation only
✅ No frontend source code modified — documentation only
✅ No new React components created — architecture proposal only
✅ Legacy UI references documented — 3 occurrences identified for removal in rebuild
FINAL STATUS

TASK-019 — FRONTEND MANAGEMENT FORENSIC AUDIT: ✅ COMPLETE

Awaiting architecture authorization before proceeding to TASK-020 implementation.
STOPPING EXECUTION AS DIRECTED.
