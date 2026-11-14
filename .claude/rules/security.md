# Security Development Rules

- Validate all incoming data.
- Apply authentication and authorization checks.
- Enforce tenant isolation on every tenant-owned resource.
- Never trust client-side authorization.
- Prevent mass assignment vulnerabilities.
- Use CSRF protection.
- Escape untrusted output.
- Validate uploaded files.
- Never expose secrets or credentials.
- Never commit environment files.
- Never disable middleware to bypass errors.
- Use secure password handling.
- Protect sensitive routes with appropriate middleware.
- Ensure public QR menu endpoints expose only intended public information.
- Test tenant isolation and authorization boundaries.