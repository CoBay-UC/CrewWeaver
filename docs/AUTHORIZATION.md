# Authorization matrix

Authorization is evaluated as visibility plus mutation scope. An active approved user can read active collaboration records across every team and can read archived history. Membership never filters ordinary reads. Unapproved or inactive users can only access their own pending-account page and authentication endpoints.

| Operation | Approved member | Participant / owner | Sub-team admin | Team admin | Global admin |
|---|---:|---:|---:|---:|---:|
| Read teams/projects/tasks/collaboration | Yes | Yes | Yes | Yes | Yes |
| Read archive | Yes | Yes | Yes | Yes | Yes |
| Comment or add research | No | Authorized resource | Own sub-team | Own team descendants | All |
| Edit assigned/owned task | No | Authorized task | Own sub-team | Own team descendants | All |
| Claim eligible unowned task | Eligible project member | Eligible | Own sub-team | Own team descendants | All |
| Manage projects/tasks/dates | No | No | Own sub-team | Own team descendants | All |
| Manage scope membership and roles | No | No | Own sub-team, limited | Own team descendants | All |
| Archive/restore/copy/replace | No | No | Own sub-team | Own team descendants | All |
| Global settings/admin roles/audit | No | No | No | No | Yes |

Roles inherit from a team to its sub-teams and their resources, never upward or sideways. Explicit cross-team assignment grants participation only on the assigned resource. It does not create team or project membership. All mutation checks are centralized in `lib/Authorization/AuthorizationService.php` and all query visibility rules are defined in `VisibilityPolicy.php`.

