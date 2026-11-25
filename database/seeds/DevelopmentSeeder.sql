-- Explicit development-only seed. Run with `php bin/seed.php` while APP_ENV=development.
INSERT INTO users (id, oidc_issuer, oidc_subject, email, first_name, last_name, display_name, status, approved_at)
VALUES
  (1, 'https://dev.invalid', 'admin', 'admin@crewweaver.test', 'Avery', 'Admin', 'Avery Admin', 'active', UTC_TIMESTAMP()),
  (2, 'https://dev.invalid', 'engineer-a', 'morgan@crewweaver.test', 'Morgan', 'Chen', 'Morgan Chen', 'active', UTC_TIMESTAMP()),
  (3, 'https://dev.invalid', 'engineer-b', 'sam@crewweaver.test', 'Sam', 'Okafor', 'Sam Okafor', 'active', UTC_TIMESTAMP()),
  (4, 'https://dev.invalid', 'pending', 'pending@crewweaver.test', 'Pending', 'Member', 'Pending Member', 'pending', NULL);

INSERT INTO scoped_roles (user_id, role) VALUES (1, 'global_admin');
INSERT INTO teams (id, name, slug, description, colour, icon, display_order) VALUES
  (1, 'Product Engineering', 'product-engineering', 'Product research and engineering.', '#4f46e5', 'gear-wide-connected', 10),
  (2, 'Operations', 'operations', 'Operational readiness and delivery.', '#059669', 'boxes', 20);
INSERT INTO sub_teams (id, team_id, name, slug, colour, display_order) VALUES
  (1, 1, 'Mechanical', 'mechanical', '#2563eb', 10),
  (2, 1, 'Electrical', 'electrical', '#7c3aed', 20),
  (3, 2, 'Launch', 'launch', '#d97706', 10);
INSERT INTO memberships (user_id, team_id, sub_team_id) VALUES (1,1,NULL),(2,1,1),(3,2,3);
INSERT INTO projects (id, team_id, sub_team_id, title, description, lead_user_id, priority, colour, status, planned_start, created_by)
VALUES
  (1,1,NULL,'Atlas Prototype','Cross-discipline prototype delivery.',2,'high','#4f46e5','Active','2027-01-01',1),
  (2,2,3,'Launch Readiness','Prepare launch processes.',3,'normal','#059669','Planned','2027-02-01',1),
  (3,1,2,'Legacy Controller','Completed controller redesign.',2,'normal','#64748b','Completed','2026-06-01',1);
UPDATE projects SET is_archived=1, archived_at=UTC_TIMESTAMP(), archived_by=1, actual_completion='2026-07-15' WHERE id=3;
INSERT INTO project_members (project_id,user_id,assigned_by) VALUES (1,1,1),(1,2,1),(1,3,1),(2,3,1),(3,2,1);
INSERT INTO tasks (id,project_id,title,status,priority,owner_user_id,planned_start,calculated_finish,estimated_duration_days,kanban_order,created_by) VALUES
  (1,1,'Product research','Done','normal',2,'2027-01-01','2027-01-03',2,1000,1),
  (2,1,'Mechanical design','In-Progress','high',2,'2027-01-03','2027-01-08',5,1000,1),
  (3,1,'Electrical design','Researching','high',NULL,'2027-01-03','2027-01-06',3,1000,1),
  (4,1,'Final review','Needs Review','critical',1,'2027-01-08','2027-01-11',3,1000,1),
  (5,2,'Publish runbook','Not Started','normal',3,'2027-02-01','2027-02-04',3,1000,1),
  (6,3,'Verify firmware','Done','normal',2,'2026-06-01','2026-06-03',2,1000,1);
INSERT INTO task_members (task_id,user_id,assigned_by) VALUES (1,2,1),(2,2,1),(3,3,1),(4,1,1),(5,3,1),(6,2,1);
INSERT INTO milestones (task_id,title,duration_days,cumulative_offset_days,calculated_date,display_order) VALUES
  (1,'Find three similar products',2,2,'2027-01-03',10),
  (2,'Concept geometry',3,3,'2027-01-06',10),
  (2,'Detailed design',2,5,'2027-01-08',20);
INSERT INTO task_dependencies (predecessor_task_id,successor_task_id,lag_days) VALUES (1,2,0),(1,3,0),(2,4,0),(3,4,0);
INSERT INTO important_dates (title,description,event_date,colour,icon,priority,scope_type,team_id,show_countdown,created_by) VALUES
  ('Prototype review','Organization-visible review deadline.','2027-01-11','#dc2626','flag','critical','team',1,1,1),
  ('Launch window','Target launch window.','2027-02-20','#d97706','rocket-takeoff','high','global',NULL,1,1);
INSERT INTO comments (task_id,author_id,body) VALUES (2,2,'Initial geometry is ready for cross-team feedback.');
INSERT INTO research_items (task_id,author_id,type,title,url,description,display_order) VALUES (1,2,'link','Reference design','https://example.com/reference','External research link.',10);
