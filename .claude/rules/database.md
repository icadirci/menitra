# Database Development Rules

- PostgreSQL is the primary database.
- Use Laravel migrations for schema changes.
- Define foreign keys and indexes appropriately.
- Use Eloquent relationships.
- Prevent N+1 queries.
- Use transactions for atomic business operations.
- Ensure tenant ownership for all tenant-specific records.
- Never trust tenant IDs from client requests.
- Use tenant-aware Redis cache keys.
- Define cache invalidation when related data changes.
- Never expose cross-tenant data.
- Do not make destructive schema changes without explicit approval.
- Do not execute production database modifications without explicit approval.