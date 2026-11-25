# HTTP API

All `/api/*` responses use `{success, message, data}` and validation errors add an `errors` object. Mutations require a valid session, `X-CSRF-Token`, and the appropriate server-side permission. List endpoints support `page`, `per_page`, `search`, and resource-specific filters.

Core endpoints:

- `/api/teams`, `/api/sub-teams`, `/api/projects`, `/api/tasks`, `/api/users`
- `/api/tasks/{id}/status`, `/owner`, `/members`, `/milestones`, `/dependencies`, `/comments`, `/research`, `/attachments`
- `/api/boards/{scope}/{id}`, `/api/gantt/{scope}/{id}`, `/api/calendar/{scope}/{id}`
- `/api/important-dates`, `/api/projects/{id}/archive`, `/restore`, `/copy`
- `/api/users/replacement/preview`, `/commit`
- `/api/notifications`, `/api/notifications/read-all`, `/api/audit`

See `app/Routes/web.php` and `app/Routes/api.php` for the exact route table.

Deleting `/api/teams/{id}` or `/api/sub-teams/{id}` requires this JSON body and the applicable administrator role.

```json
{"confirmation":"I understand this permanently deletes the data and cannot be undone."}
```

The operation removes projects in that scope, including archived projects. The response reports deleted project, task, and attachment counts and any files still queued for storage cleanup.

