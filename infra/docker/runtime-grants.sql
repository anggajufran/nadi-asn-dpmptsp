-- Run as database owner only, after migrations/restore. Never put owner credentials in app.env.
REVOKE ALL ON DATABASE nadi_asn FROM PUBLIC;
GRANT CONNECT ON DATABASE nadi_asn TO nadi_runtime;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO nadi_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nadi_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nadi_runtime;
REVOKE ALL ON migrations FROM nadi_runtime;
GRANT SELECT ON migrations TO nadi_runtime;
REVOKE UPDATE, DELETE ON audit_events, published_snapshots, public_releases, duk_candidates FROM nadi_runtime;
-- No default DDL/TRUNCATE/REFERENCES/TRIGGER permissions; reapply after future migrations.
