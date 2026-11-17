# IMPLEMENTATION PLAN --- KỊCH BẢN SÁT V16

**Document:** Implementation & Migration Plan\
**Version:** V16 Draft 0.1\
**Status:** Draft for Review\
**Companion specification:** `PRODUCT_SPEC_V16_Draft.md`\
**Business baseline:** `PRODUCT_SPEC_V15_Final.md`\
**Team:** 6 members --- 2 Backend, 3 Frontend, 1 Documentation/QA
coordination\
**Target:** MVP-first, 4-week target subject to Phase 0 re-estimation\
**Environment:** Windows native, VS Code, Antigravity, Git/GitHub, no
Docker

------------------------------------------------------------------------

## 1. Purpose

Tài liệu này chuyển PRODUCT_SPEC_V16 thành kế hoạch triển khai có thể
giao việc cho 6 thành viên.

Mục tiêu: - Bảo toàn nghiệp vụ đã Accepted trong V15. - Chuyển công nghệ
từ Next.js/React/TypeScript + Java/Spring Boot + PostgreSQL sang PHP
thuần MVC + REST API + HTML/CSS/JavaScript + MySQL. - Chuyển đổi có kiểm
soát, không dịch source từng file. - Chia task theo owner, dependency,
deliverable và acceptance criteria. - Tích hợp theo vertical slice để
sớm phát hiện lỗi contract và state machine.

**Quy tắc:** Đây là kế hoạch đề xuất. Không đánh dấu task complete khi
chưa có bằng chứng; không tự biến open decision thành quyết định đã
Accepted.

------------------------------------------------------------------------

## 2. Team structure và ownership

### 2.1. Vai trò

  -----------------------------------------------------------------------
  ID                      Role                    Primary ownership
  ----------------------- ----------------------- -----------------------
  BE-1                    Backend Engineer 1      PHP core, Identity,
                                                  Authentication,
                                                  Authorization,
                                                  Security, API
                                                  conventions

  BE-2                    Backend Engineer 2      Domain modules,
                                                  business workflows,
                                                  database integration,
                                                  jobs

  FE-1                    Frontend Engineer 1     Frontend foundation,
                                                  API client, shared UI,
                                                  integration
                                                  coordination

  FE-2                    Frontend Engineer 2     Public Home + Player
                                                  Portal

  FE-3                    Frontend Engineer 3     DM Portal + Admin
                                                  Portal

  DOC-1                   Documentation/QA        Product/technical docs,
                          Coordinator             ERD/API docs
                                                  coordination, test
                                                  evidence, release
                                                  checklist
  -----------------------------------------------------------------------

DOC-1 không phải người duy nhất chịu trách nhiệm chất lượng. Mỗi feature
owner phải viết/duy trì test và cung cấp bằng chứng. DOC-1 điều phối và
kiểm tra tính đầy đủ.

### 2.2. Responsibility matrix

  Workstream                         Responsible              Reviewer/Partner
  ---------------------------------- ------------------------ -------------------
  PHP MVC core                       BE-1                     BE-2
  PDO/database infrastructure        BE-2                     BE-1
  Identity/session/security          BE-1                     FE-1
  Game/GameRoom/Participant          BE-2                     BE-1
  DM/Venue/Session                   BE-2                     BE-1
  Payment/Refund/Jobs                BE-2                     BE-1
  Frontend architecture/API client   FE-1                     BE-1
  Player Portal                      FE-2                     FE-1
  DM Portal                          FE-3                     FE-1
  Admin Portal                       FE-3                     FE-1
  ERD/data dictionary                BE-2 (technical owner)   BE-1 + DOC-1
  API contract/OpenAPI               BE-1 (technical owner)   BE-2 + FE-1
  Test plan/evidence                 Feature owner            DOC-1 coordinates
  Product spec/version governance    DOC-1                    Team lead/owners
  MVP acceptance                     Whole team               Product owner

------------------------------------------------------------------------

## 3. Delivery principles

1.  **V15 is the business source of truth.** V16 changes implementation
    technology, not accepted business rules.
2.  **One official repository.** Work only in
    `nguyenxuanhieu2903/kich-ban-sat-php`; reference repositories are
    audit-only.
3.  **No big-bang migration.** Build a runnable skeleton, then vertical
    slices.
4.  **Contract before integration.** Frontend and Backend must agree
    request/response/auth/error behavior before wiring.
5.  **Backend authoritative.** Frontend checks improve UX; Backend
    validates every operation.
6.  **No framework.** No Laravel/Symfony/CodeIgniter, no
    React/Next/Vue/Angular.
7.  **No Docker.** Local environment runs native on Windows.
8.  **Small PRs.** One feature or coherent change per PR, reviewed by
    another member.
9.  **Tests travel with code.** Every business transition includes
    tests.
10. **Evidence-based status.** No PASS without executed test output or
    reproducible manual evidence.
11. **Do not overbuild the framework.** Implement only the Router,
    Request/Response, Middleware, Validator, DI/bootstrap and exception
    handling needed by the product.
12. **Open questions stay open.** Record owner and due date; do not
    silently choose product behavior.

------------------------------------------------------------------------

## 4. Repository and Git workflow

### 4.1. Baseline protection

Before code changes: - Confirm official repository and current default
branch. - Confirm clean working tree. - Create a backup/checkpoint
branch or tag for V15 source. - Preserve V15 specification and
supporting accepted documents. - Record current commit hash. - Audit
source before deciding reuse/rewrite/discard.

Suggested branches:

``` text
main
develop
feature/backend-php-core
feature/backend-identity
feature/backend-game-room
feature/backend-session
feature/backend-payment
feature/frontend-foundation
feature/frontend-player
feature/frontend-dm-admin
docs/v16-migration-plan
```

Branch names are examples; adapt to current team convention.

### 4.2. Pull Request policy

Every PR must include: - Purpose and scope. - Related task/issue. -
Files/modules changed. - API/DB impact. - Security impact. - Tests
executed and result. - Screenshots for UI changes where useful. -
Migration/rollback notes if schema changes. - Reviewer approval.

Do not merge a PR that changes a public API contract without updating
API documentation and dependent frontend code.

### 4.3. Commit convention

Suggested format:

``` text
feat(game-room): implement room creation
fix(payment): make deposit confirmation idempotent
test(room-participant): cover concurrent rejoin
docs(api): update authentication contract
refactor(core): centralize exception mapping
```

------------------------------------------------------------------------

## 5. Migration strategy V15 → V16

Use **Controlled Rebuild**.

### 5.1. Preserve

Keep: - V15 product specification and accepted design documents. -
Business state machines and transition matrices. - Domain terminology
and business rules. - Existing repository history and baseline
checkpoint. - Useful UX/business findings discovered during source
audit.

### 5.2. Rebuild

Rebuild or redesign: - Backend runtime and MVC core. - Database
schema/migrations for MySQL. - REST API contract. - Frontend
implementation. - Authentication/authorization implementation. -
Background job execution. - Test automation appropriate to the new
stack.

### 5.3. Audit classifications

For each existing source artifact, mark one: - `REUSE`: independent
asset/document that remains valid. - `ADAPT`: useful concept/data/UI
behavior but needs redesign. - `REWRITE`: behavior must be rebuilt for
the new architecture. - `DISCARD`: obsolete, duplicate, or inconsistent
with V15.

Do not port old endpoint contracts or database schema automatically. V15
explicitly requires the group to design its own ERD and API contract.

------------------------------------------------------------------------

## 6. Phase plan and task breakdown

## Phase 0 --- Freeze V15 and source audit

**Target:** Pre-week / Day 0--1\
**Goal:** Establish a safe migration baseline.

  --------------------------------------------------------------------------------------------------
  Task                             Owner             Output            Acceptance
  -------------------------------- ----------------- ----------------- -----------------------------
  Confirm official repo, branch    DOC-1 + all       Baseline record   Repo/branch/commit documented
  and working tree                                                     

  Create backup/tag/checkpoint     BE-1              V15 checkpoint    Revert path verified

  Inventory current                FE-1 + BE-2       Source inventory  All major folders classified
  frontend/backend/database/docs                                       

  Audit existing screens and flows FE-2 + FE-3       UI/flow audit     Mismatches and reusable
  against V15                                                          concepts recorded

  Audit existing backend/domain    BE-1 + BE-2       Backend audit     Reuse/adapt/rewrite/discard
  logic                                                                assigned

  List environment and dependency  BE-1              Environment       Windows setup prerequisites
  requirements                                       checklist         known

  Create V16 docs folder and       DOC-1             Docs structure    V15 preserved; V16 separate
  versioning rules                                                     
  --------------------------------------------------------------------------------------------------

**Exit criteria** - \[ \] V15 baseline can be restored. - \[ \] Official
repository confirmed. - \[ \] Source audit completed. - \[ \] No
unreviewed destructive deletion. - \[ \] Work items and owners recorded.

------------------------------------------------------------------------

## Phase 1 --- PHP MVC and frontend foundation

**Target:** Week 1\
**Goal:** A working browser-to-API-to-MySQL vertical foundation.

### Backend tasks

**BE-1 --- PHP core** - Install/verify PHP CLI and required extensions
on Windows. - Configure Composer and PSR-4 autoload. - Implement
`public/index.php` front controller. - Implement Router: method/path
matching, route parameters, 404/405. - Implement Request and Response
abstractions. - Implement middleware pipeline. - Implement centralized
exception handler. - Implement JSON response helper/convention. -
Implement configuration loader using environment variables. - Add health
endpoint, e.g. `GET /api/v1/health`. - Add initial PHPUnit setup and
smoke test.

**BE-2 --- Database infrastructure** - Install/verify MySQL native on
Windows. - Define local DB connection settings via environment
variables. - Implement PDO connection factory. - Set PDO exception mode
and safe fetch behavior. - Implement transaction helper or clear
transaction boundary pattern. - Verify prepared statement usage. -
Evaluate migration tool options; do not lock without team review. -
Prepare initial database connectivity test.

### Frontend tasks

**FE-1 --- Frontend foundation** - Create static frontend entry point. -
Define CSS reset/base tokens/layout conventions. - Define JS ES Module
structure. - Implement shared API client. - Implement centralized API
error handling. - Implement shared loading/empty/error UI patterns. -
Decide local frontend/API serving topology with BE-1. - Implement a
health-check screen to verify API connectivity.

**FE-2 --- Public and Player shell** - Create public page skeleton. -
Create login/register page skeleton. - Create Player layout/navigation
shell. - Define responsive breakpoints and basic accessibility
conventions.

**FE-3 --- DM/Admin shell** - Create DM layout/navigation shell. -
Create Admin layout/navigation shell. - Define shared table/form/modal
patterns with FE-1. - Use mock data only for layout prototyping; mark it
clearly and remove it before integration.

### Documentation tasks

**DOC-1** - Update V16 change log. - Write Windows setup guide draft. -
Record runtime versions after team confirmation. - Create ADR
template. - Create task board with owner/status/dependency. - Create
test evidence template.

### Phase 1 acceptance

-   [ ] PHP front controller runs.
-   [ ] Router handles known route and 404.
-   [ ] JSON health endpoint returns expected schema.
-   [ ] PDO connects to local MySQL.
-   [ ] Composer autoload works.
-   [ ] PHPUnit smoke test executes.
-   [ ] Static frontend loads in browser.
-   [ ] Frontend can call health endpoint.
-   [ ] Local origin/cookie/CORS approach is documented.
-   [ ] No framework or Docker introduced.

------------------------------------------------------------------------

## Phase 2 --- Database design

**Target:** Week 1, parallel with foundation\
**Goal:** An agreed MySQL schema that represents V15 domain states and
invariants.

### BE-2 --- Database owner

-   Build domain data inventory.
-   Draft ERD and relationships.
-   Define table/column naming conventions.
-   Define PK/FK strategy.
-   Define monetary types and rounding representation.
-   Define status storage strategy (string/enum policy) without losing
    extensibility.
-   Define unique constraints for active participation and other
    invariants.
-   Define indexes from query/use-case inventory.
-   Define audit/history retention model.
-   Define transaction boundaries.
-   Define locking strategy for join/rejoin/payment/cancel.
-   Create migrations after schema review.
-   Prepare deterministic seed data for development/test.

### BE-1 --- Review and security

-   Review identity/session schema.
-   Review role mapping and authorization data.
-   Review constraints related to security-sensitive ownership.
-   Review transaction and locking plan.
-   Review migration execution/rollback strategy.
-   Ensure secrets are not stored in repository.

### FE-1/FE-2/FE-3

-   Provide screen data requirements and query use cases.
-   Identify list/detail/filter/pagination needs.
-   Do not dictate schema from UI convenience alone.
-   Confirm frontend display fields and empty states.

### DOC-1

-   Maintain ERD, Data Dictionary and schema decision log.
-   Record unresolved questions.
-   Verify migration steps are reproducible from an empty database.

### Phase 2 acceptance

-   [ ] ERD reviewed by BE-1 and BE-2.
-   [ ] Data Dictionary covers agreed tables/columns.
-   [ ] Constraints/indexes documented with reasons.
-   [ ] Critical transaction/locking boundaries documented.
-   [ ] Migration tool selected and approved.
-   [ ] Fresh database migration succeeds.
-   [ ] Seed data can be recreated.
-   [ ] V15 state/history requirements are represented.
-   [ ] No schema copied blindly from a reference repository.

------------------------------------------------------------------------

## Phase 3 --- API contract and job design

**Target:** End of Week 1 / early Week 2\
**Goal:** Freeze the first integration contract before parallel feature
development.

### BE-1 --- API contract owner

-   Define API base path and naming conventions.
-   Define authentication/session endpoints.
-   Define common success/error response.
-   Define status code mapping.
-   Define request validation format.
-   Define authentication and authorization requirements per endpoint.
-   Define CSRF behavior.
-   Define pagination/filter/sort conventions.
-   Define API versioning.
-   Define idempotency for commands with side effects.
-   Produce OpenAPI document or equivalent endpoint specification.

### BE-2 --- Domain/job contract

-   Map V15 use cases to commands/queries.
-   Define domain error cases and conflict cases.
-   Define transaction/locking note per critical command.
-   Define job catalog: formation, deposit deadline, reminders,
    replacement, reconciliation, notification.
-   Define idempotency/retry requirements.
-   Define time zone and deadline comparison policy proposal.
-   Define late payment handling.
-   Identify unresolved product questions requiring decision.

### FE-1/FE-2/FE-3

-   Review API shapes against actual screens.
-   Define frontend API method requirements.
-   Identify required pagination and filter parameters.
-   Create API integration checklist.
-   Do not start production integration against undocumented endpoints.

### DOC-1

-   Maintain API endpoint inventory.
-   Maintain authorization matrix.
-   Record OpenAPI version and change log.
-   Maintain job catalog and unresolved-decision list.

### Phase 3 acceptance

-   [ ] Every P0 screen action maps to an API operation or documented
    navigation.
-   [ ] Every API has request/response/error definitions.
-   [ ] Authentication and authorization requirements are explicit.
-   [ ] IDOR/resource ownership checks are explicit.
-   [ ] Critical commands have transaction/idempotency notes.
-   [ ] Jobs have trigger, policy, idempotency and failure behavior.
-   [ ] FE and BE approve the contract.
-   [ ] Contract changes use review/versioning.

------------------------------------------------------------------------

## Phase 4 --- Backend implementation

**Target:** Week 2--3\
**Goal:** Implement backend features incrementally, with tests.

### BE-1 --- Core, Identity, Security and API

**Feature A: Core** - Complete Router and middleware pipeline. - Add
route grouping/version prefix. - Add request ID/correlation logging if
feasible. - Add centralized error mapping. - Add request body parsing
and content-type validation. - Add safe configuration/bootstrap.

**Feature B: Identity** - Registration validation. - Password
hashing/verification. - Login/logout. - Session rotation and
expiration. - Current-user endpoint. - Profile read/update. - Role
resolution. - Authentication middleware. - Role/resource authorization
middleware or policy services.

**Feature C: Security** - CSRF protection for cookie session. - Login
rate limiting. - IDOR tests. - Safe output/error handling. - Security
configuration review. - Audit authentication-sensitive actions as
required.

**Tests** - Registration/login success and invalid credentials. -
Session fixation prevention. - Unauthorized/forbidden access. - Role
escalation attempts. - CSRF rejection. - Rate limit behavior. - Profile
ownership checks.

### BE-2 --- Domain modules and workflows

Implement in this order, adjusted only by dependency: 1. Game. 2.
GameRoom. 3. RoomParticipant. 4. DM/DMAssignment. 5. Venue. 6.
ScheduleProposal/Session. 7. PlayerPayment. 8. Refund. 9. Notification.
10. Audit. 11. Background jobs.

For each module: - Implement repository queries. - Implement
validators. - Implement service/use case. - Implement domain transition
guards. - Implement transaction and locking. - Implement API
controller. - Add unit and integration tests. - Add audit and
notification side effects according to V15.

**Critical implementation rules** - Do not implement state transition in
Frontend. - Do not use blind status updates. - Read current state inside
transaction. - Protect final slot and active participation
constraints. - Duplicate command/callback must not duplicate side
effects. - Terminal participant record is never reactivated. - Rejoin
creates a new participant record. - Admin-required reason must be
validated. - Notification failure must not undo committed business
operation.

### Backend Definition of Done

-   [ ] API contract followed.
-   [ ] Authentication/authorization enforced.
-   [ ] Validation at server boundary.
-   [ ] Business logic in service/domain.
-   [ ] Repository uses PDO prepared statements.
-   [ ] Transaction/locking applied where required.
-   [ ] Errors mapped consistently.
-   [ ] Audit/notification side effects handled.
-   [ ] Unit tests added and executed.
-   [ ] Integration tests added and executed where applicable.
-   [ ] No secrets or debug traces exposed.

------------------------------------------------------------------------

## Phase 5 --- Frontend implementation

**Target:** Week 2--3, parallel with Backend once API contract is
approved.

### FE-1 --- Shared frontend platform

-   Complete API client.
-   Centralize auth state retrieval/refresh behavior appropriate to PHP
    session.
-   Implement common navigation/layout utilities.
-   Implement shared form, table, badge, modal and notification
    components as lightweight JS modules where useful.
-   Implement error/empty/loading/success patterns.
-   Implement CSRF token handling.
-   Implement pagination/filter helpers.
-   Establish accessibility and responsive review checklist.
-   Coordinate integration and remove temporary mock data.

### FE-2 --- Public Home + Player Portal

-   Public home page.
-   Game list/detail.
-   Public GameRoom list/detail.
-   Register/login/logout UI.
-   Player profile.
-   Create GameRoom.
-   Join GameRoom.
-   Participant status and room detail.
-   Leave request/rejoin flows.
-   Payment status and mock payment actions.
-   Notification Center.
-   Player history.

### FE-3 --- DM Portal + Admin Portal

**DM** - DM dashboard. - Assignment list/detail. - Accept/reject
assignment. - Availability management. - Session/participant views. -
Confirm in-person payment where authorized. - Notification views.

**Admin** - Admin dashboard. - User/role management. - Game
management. - GameRoom management. - DM assignment/revoke. -
Venue/PhysicalRoom management. - Schedule/Session management. -
Payment/refund management. - Leave approval/rejection. - Cancel/reopen
flows with mandatory reason. - Audit views.

### Frontend Definition of Done

-   [ ] Data loaded from API, not hardcoded.
-   [ ] All actions map to approved API operations.
-   [ ] Permission-aware UI is implemented for UX only.
-   [ ] Backend still enforces authorization.
-   [ ] Loading/empty/error/success states exist.
-   [ ] Form validation gives useful feedback.
-   [ ] Duplicate submit is handled in UI and safely handled in Backend.
-   [ ] Responsive layout reviewed.
-   [ ] No secret or password in localStorage.
-   [ ] Keyboard/focus basics checked.
-   [ ] UI tests/manual evidence recorded.

------------------------------------------------------------------------

## Phase 6 --- Integration, background jobs and end-to-end

**Target:** Week 4\
**Goal:** Prove complete P0 workflows.

### Backend

-   Implement CLI job entrypoints.
-   Implement formation timeout.
-   Implement participant-specific deposit deadline.
-   Implement deposit reminders.
-   Implement replacement window expiry.
-   Implement notification dispatch.
-   Implement payment/refund reconciliation as required by MVP.
-   Add job lock/idempotency.
-   Add logs and failure visibility.
-   Verify timezone/clock policy.

### Frontend

-   Complete end-to-end integration.
-   Remove all temporary mock data.
-   Verify session behavior across reload/navigation.
-   Verify error and conflict handling.
-   Verify polling frequency and stop/backoff behavior.
-   Verify Player/DM/Admin role navigation.

### Integration scenarios

1.  Player registration/login/logout.
2.  Player creates GameRoom.
3.  Another player joins.
4.  Concurrent final-slot join attempts.
5.  Admin organizes DM and venue.
6.  DM accepts assignment.
7.  Schedule is confirmed according to V15.
8.  Deposit opens and participant pays.
9.  Deposit deadline expires for a different participant.
10. Replacement window opens/expires/reopens.
11. Participant rejoin creates new record.
12. Admin cancels room and refund workflow starts.
13. Leave request is approved/rejected with reason.
14. Duplicate payment/refund action does not duplicate effects.
15. Notification failure does not rollback committed business state.

### Phase 6 acceptance

-   [ ] P0 end-to-end flows pass.
-   [ ] Critical concurrency tests pass.
-   [ ] Job idempotency tests pass.
-   [ ] No duplicate side effects.
-   [ ] No stale state transition.
-   [ ] No unauthorized resource access.
-   [ ] No booking confirmation after failed persistence.
-   [ ] Logs permit investigation of failed job/API request.

------------------------------------------------------------------------

## Phase 7 --- Regression, security and MVP release

**Target:** End of Week 4 (subject to scope and test results)

### All engineers

-   Fix P0 defects.
-   Run full test suite.
-   Run fresh database setup.
-   Run clean local setup using README.
-   Verify no accidental framework/Docker dependency.
-   Verify `.env`/secrets are excluded from Git.
-   Review critical authorization paths.
-   Review payment/refund and state machine edge cases.

### DOC-1

-   Collect test evidence.
-   Update known issues.
-   Update README.
-   Update API/ERD docs to match implementation.
-   Prepare demo script.
-   Prepare MVP acceptance checklist.
-   Prepare release notes and rollback/checkpoint reference.

### Release gate

-   No open critical/high security issue.
-   P0 acceptance criteria have evidence.
-   Fresh setup succeeds.
-   API and database docs match deployed code.
-   Product owner accepts known limitations.
-   No claim of PASS for unexecuted tests.

------------------------------------------------------------------------

## 7. Four-week execution calendar

The 4-week target is inherited from V15. It is an execution target, not
a guarantee. Re-estimate after Phase 0 and Phase 1 based on team
availability, source audit and learning curve.

  ---------------------------------------------------------------------------------------------------------------------------------------
  Week       Backend 1              Backend 2        Frontend 1     Frontend 2                           Frontend 3       Documentation
  ---------- ---------------------- ---------------- -------------- ------------------------------------ ---------------- ---------------
  1          PHP core, Router,      PDO, MySQL,      API client,    Public/Player shell                  DM/Admin shell   Freeze/audit,
             Middleware, Identity   ERD/schema draft frontend                                                             setup guide,
             skeleton                                foundation                                                           ERD/API
                                                                                                                          templates

  2          Auth/security, API     Game, GameRoom,  Shared UI +    Player auth/Game/GameRoom            DM/Admin base    API contract,
             conventions            Participant      integrate                                           features         test plan,
                                                     Identity                                                             decision log

  3          Authorization,         DM, Venue,       Cross-portal   Player                               DM/Admin         Test evidence,
             hardening, feature     Session,         integration    payment/leave/rejoin/notifications   workflows        data
             review                 Payment, Refund                                                                       dictionary, job
                                                                                                                          catalog

  4          Integration fixes,     Jobs,            E2E            Player E2E/regression                DM/Admin         Release
             security/concurrency   idempotency,     integration                                         E2E/regression   checklist,
             tests                  reconciliation   and UX fixes                                                         README, MVP
                                                                                                                          report
  ---------------------------------------------------------------------------------------------------------------------------------------

### Capacity warning

-   Nếu team mới làm PHP, dành thời gian spike/learning ở Phase 1.
-   Không triển khai mọi module cùng lúc.
-   Nếu không đủ thời gian, cắt scope ngoài P0; không cắt transaction,
    authorization, audit hoặc test critical state transitions.
-   Không giảm yêu cầu bảo vệ tiền và dữ liệu chỉ để giữ timeline.

------------------------------------------------------------------------

## 8. Vertical slice strategy

Không nên hoàn thành toàn bộ Backend trước rồi mới bắt đầu Frontend. Làm
theo vertical slice:

### Slice 1 --- Identity

-   Register/Login/Logout.
-   Session.
-   Current user.
-   Frontend auth UI.
-   Authorization smoke tests.

### Slice 2 --- GameRoom core

-   Game list/detail.
-   Create GameRoom.
-   Join GameRoom.
-   Participant list.
-   State/condition validation.
-   Final-slot concurrency test.

### Slice 3 --- Organization

-   DM assignment.
-   Accept/reject.
-   Venue.
-   Schedule proposal/confirmation.
-   Admin/DM/Player views.

### Slice 4 --- Money and lifecycle

-   Deposit.
-   Deadline.
-   Remaining payment.
-   Leave/rejoin.
-   Replacement.
-   Refund.
-   Idempotency/race tests.

### Slice 5 --- Operational completion

-   Notification.
-   Audit.
-   Background jobs.
-   Full E2E/regression.

Each slice must be demoable with real database and approved API
contract.

------------------------------------------------------------------------

## 9. Dependency map

``` text
Phase 0: Freeze + Audit
       |
       v
Phase 1: PHP Core + Frontend Foundation
       |
       +-----------------------+
       |                       |
       v                       v
Phase 2: Database Design   Frontend Shell
       |                       |
       +-----------+-----------+
                   |
                   v
          Phase 3: API Contract
                   |
          +--------+--------+
          |                 |
          v                 v
   Backend Features   Frontend Features
          |                 |
          +--------+--------+
                   |
                   v
       Phase 6: Integration + Jobs
                   |
                   v
       Phase 7: Regression + MVP
```

Phase 2/3 decisions must be available before dependent feature work is
considered complete. UI prototyping may proceed with clearly marked mock
data, but integration must use the approved contract.

------------------------------------------------------------------------

## 10. Risk register

  -------------------------------------------------------------------------------------------
  Risk              Impact                         Mitigation               Owner
  ----------------- ------------------------------ ------------------------ -----------------
  PHP learning      Schedule delay, inconsistent   Small PHP spike, coding  BE-1
  curve             code                           conventions, pair review 

  Self-built MVC    Maintenance burden             Keep core minimal; no    BE-1
  grows too large                                  generic framework        
                                                   features                 

  API contract      FE/BE rework                   Contract review and      BE-1 + FE-1
  changes late                                     versioning before        
                                                   integration              

  Schema misses V15 Data corruption                ERD review,              BE-2
  invariant                                        constraint/transaction   
                                                   test                     

  Race condition in Overbooking or wrong state     Transaction, locks,      BE-2
  join/payment                                     unique constraints,      
                                                   concurrency tests        

  Session/CORS      Login integration failure      Prefer same-origin;      BE-1 + FE-1
  mismatch                                         explicitly test          
                                                   cookies/CSRF             

  Background jobs   Duplicate                      Idempotency, job lock,   BE-2
  run twice         cancellation/reminder/refund   execution logs           

  Frontend          Inconsistent state             Backend authoritative;   FE-1
  duplicates                                       contract tests           
  business rules                                                            

  Four-week target  Incomplete MVP                 Re-estimate after audit; Whole team
  too aggressive                                   prioritize P0            

  Unclear V15 open  Rework                         Decision log and         DOC-1 + product
  decisions                                        owner/deadline           owner

  Untracked         Security incident              `.env.example`,          BE-1
  secret/config                                    gitignore, review        
  -------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 11. Definition of Ready (DoR)

A task is Ready only when: - Requirement is traceable to V15/V16 or
approved change. - Acceptance criteria are written. - Owner and reviewer
are assigned. - Dependencies are known. - API/DB contract impact is
identified. - Open questions are resolved or explicitly excluded from
the task. - Test expectation is known.

## 12. Definition of Done (DoD)

A task is Done only when: - Implementation is complete. - Code follows
module/layer responsibility. - Validation and authorization are
implemented server-side. - Error cases are handled. - Tests are added
and executed. - Reviewer approves PR. - Documentation is updated where
required. - No secret/debug artifact is committed. - Manual evidence is
attached for UI/E2E tasks. - No acceptance criterion is silently
skipped.

------------------------------------------------------------------------

## 13. Test matrix

  ----------------------------------------------------------------------------
  Area                    Happy path                   Edge/security cases
  ----------------------- ---------------------------- -----------------------
  Identity                Register/login/logout        Invalid credentials,
                                                       session fixation, CSRF,
                                                       rate limit

  Authorization           Correct role action          IDOR, role escalation,
                                                       wrong resource owner

  GameRoom                Create/view                  Creator limit, invalid
                                                       game condition, cancel
                                                       race

  Participant             Join/leave/rejoin            Final slot race,
                                                       duplicate join,
                                                       terminal record
                                                       reactivation

  DM                      Accept/reject assignment     Competing assignment
                                                       actions, revoked
                                                       assignment

  Venue                   Reserve room                 Overlapping
                                                       reservation, capacity
                                                       conflict

  Schedule                Proposal/confirm             Reject, timeout,
                                                       concurrent proposal

  Payment                 Deposit/remaining payment    Duplicate callback,
                                                       late callback, deadline
                                                       race

  Refund                  Request/succeed/fail/retry   Duplicate retry, old
                                                       refund vs new
                                                       participation

  Jobs                    Deadline/reminder            Duplicate execution,
                                                       stale state, retry

  Notification            List/read                    Dispatch failure,
                                                       duplicate notification

  Audit                   Record action                Missing actor/reason,
                                                       unauthorized read

  Frontend                Form and navigation          API
                                                       401/403/409/422/500,
                                                       slow network, duplicate
                                                       click
  ----------------------------------------------------------------------------

------------------------------------------------------------------------

## 14. Weekly ceremonies and reporting

Suggested lightweight cadence: - Daily: 10-minute blocker sync. - Twice
weekly: FE/BE contract sync. - End of week: demo working vertical
slices. - End of week: update risk register, task status and test
evidence.

Status values: - `NOT_STARTED` - `IN_PROGRESS` - `BLOCKED` -
`IN_REVIEW` - `DONE` - `NOT_APPLICABLE`

Every `BLOCKED` task must include blocker, owner and next action.

Weekly report: - Completed deliverables. - Tests executed and result. -
Current blockers. - API/DB changes. - Decisions needed. - Scope/timeline
risks. - Next-week commitments.

------------------------------------------------------------------------

## 15. MVP release checklist

### Environment

-   [ ] PHP CLI/version and extensions documented.
-   [ ] Composer install works from clean checkout.
-   [ ] MySQL version/config documented.
-   [ ] Fresh database migration succeeds.
-   [ ] Frontend static server/origin documented.
-   [ ] Windows setup guide verified by another member.
-   [ ] No Docker required.

### Backend

-   [ ] Router and middleware work.
-   [ ] JSON response/error convention consistent.
-   [ ] PDO prepared statements used.
-   [ ] Authentication/session secure.
-   [ ] Authorization enforced per resource.
-   [ ] Transactions/locks for critical use cases.
-   [ ] Idempotency for side-effect commands.
-   [ ] Jobs have safe retry/idempotency.
-   [ ] Logs exclude secrets.

### Frontend

-   [ ] Player Portal P0.
-   [ ] DM Portal P0.
-   [ ] Admin Portal P0.
-   [ ] All production flows use API.
-   [ ] Loading/empty/error/success states.
-   [ ] No business decision is trusted from client.
-   [ ] Responsive and basic accessibility review.

### Business and quality

-   [ ] V15 state machines preserved.
-   [ ] P0 business flows tested.
-   [ ] Concurrency edge cases tested.
-   [ ] Payment/refund edge cases tested.
-   [ ] Audit and notifications tested.
-   [ ] Security checks have evidence.
-   [ ] E2E demo completed.
-   [ ] Known limitations accepted.
-   [ ] Product owner accepts MVP.

------------------------------------------------------------------------

## 16. Decisions required before execution

  --------------------------------------------------------------------------
  Decision             Proposed owner    Needed by         Status
  -------------------- ----------------- ----------------- -----------------
  Exact PHP            BE-1              Phase 1           Open
  version/extensions                                       

  MySQL exact version  BE-2              Phase 2           Open

  Migration tool       BE-2              Phase 2           Open

  Same-origin vs       BE-1 + FE-1       Phase 1           Open
  separate-origin                                          
  local setup                                              

  Session/CSRF details BE-1              Phase 3           Open

  API response/status  BE-1 + FE-1       Phase 3           Open
  conventions                                              

  Polling interval     FE-1 + BE-1       Phase 3           Open

  Job runner/retry     BE-2              Phase 3           Open
  strategy                                                 

  Six V15 open         Product owner +   Before affected   Open
  business questions   DOC-1             feature           

  Hosting/deployment   Team              After MVP         Deferred
                                         stabilization     
  --------------------------------------------------------------------------

------------------------------------------------------------------------

## 17. Final recommendation

1.  Complete Phase 0 before deleting or replacing existing source.
2.  Use the first 1--2 days to prove PHP MVC, PDO, MySQL, Composer and
    one browser-to-API request.
3.  Approve ERD and API contract before large-scale parallel feature
    implementation.
4.  Build GameRoom as the first business vertical slice after Identity.
5.  Treat payment, refund, deadline, cancellation, rejoin and
    concurrency as high-risk code paths requiring explicit tests.
6.  Re-estimate the four-week target after source audit and PHP
    foundation spike.
7.  Release only when P0 acceptance criteria have execution evidence.

**Document status: Draft for Review.** The team should review owners,
capacity, PHP/MySQL versions, migration strategy and unresolved product
decisions before treating this plan as committed.
