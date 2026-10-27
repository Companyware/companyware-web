# Roles, permissions and plugin privileges

Companyware enforces authorization on the server. Navigation visibility is only a usability layer and never the security boundary.

Core and plugins use stable privilege keys in the form `<owner>.<action>`. Plugins declare every privilege in `plugin.json` with a translated label and group. Navigation entries may reference one of the declaring plugin's privileges. Package validation rejects malformed or undeclared references.

Controller actions use `#[RequiresPrivilege('owner.action')]` when one privilege covers every allowed HTTP method. Actions that distinguish read and write operations call `AuthorizationInterface::denyUnlessGranted()` at the operation boundary. Slot providers may implement `PrivilegeAwareInterface`; Core then suppresses unauthorized contributions before rendering them.

Roles group privileges and are assigned many-to-many to administrators. The protected `system-administrator` role grants every current and future privilege. Deploying the initial authorization migration assigns every existing administrator to that role, preventing rollout lockout. The final system administrator cannot be removed, protected privileges cannot be delegated to ordinary roles and the recovery capability remains Core-owned.

The administration renders every ordinary role as a compact editor. Permissions are grouped by package owner and area, while technical keys remain available only as hover context. Administrators select registered permissions for one role and save that role directly; they cannot create free-form privilege keys. Packages must translate both each declared label and `security.group.<group>` for their group heading.

Record scopes such as `own`, `department` and `all` are deliberately not encoded as additional ad-hoc privilege keys. They require a separate policy and context contract so repositories and application services can enforce the same data boundary; this is tracked as a follow-up work package.

The authenticated dashboard shell remains the safe landing page even for accounts without additional privileges. Its plugin contributions are filtered independently; customization still requires `core.dashboard.customize`.

Plugin privilege rows and their role assignments are removed through foreign-key cascades during complete plugin uninstall. Deactivation preserves assignments. Installation and access management synchronize declared privileges.

Role creation, privilege changes, role assignments and plugin lifecycle mutations are written to `security_audit` with actor, target and timestamp.
