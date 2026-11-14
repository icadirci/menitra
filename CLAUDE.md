# CLAUDE.md — QR Menu SaaS Platform

## 1. Project Overview

This project is a multi-tenant SaaS platform for cafes, restaurants, coffee shops, bakeries and other hospitality businesses.

The platform allows businesses to create and publish digital menus accessible through QR codes.

The system consists of:

1. Public SaaS marketing website.
2. User registration and authentication.
3. Subscription and pricing pages.
4. Customer-facing digital QR menus.
5. Multiple customizable menu themes.
6. Multi-tenant infrastructure.

IMPORTANT:
The administration dashboard and internal management interfaces are outside the current development scope. Do not create admin dashboard pages unless explicitly requested.

Focus on building a scalable, maintainable and production-ready application.

## 2. Technology Stack

The project MUST use the following technologies:

### Backend
- PHP 8.4+
- Laravel 12
- PostgreSQL
- Redis
- Laravel Eloquent ORM
- Laravel Form Requests
- Laravel Policies and Gates where appropriate
- Laravel Queues when asynchronous processing is needed

### Frontend
- Laravel Blade
- Tailwind CSS
- Alpine.js
- Vite
- Vanilla JavaScript when appropriate

### Architecture
- Server-side rendering with Blade.
- Modular and reusable components.
- Multi-tenant architecture.
- RESTful APIs only when genuinely needed.
- Redis-based caching for frequently accessed menu data.

### Technology Restrictions

Do NOT introduce the following technologies unless explicitly requested:

- React
- Vue.js
- Next.js
- Nuxt.js
- Inertia.js
- Livewire
- Bootstrap
- jQuery

Do not introduce unnecessary frontend frameworks or libraries.

Do not replace the existing Laravel architecture with another architecture.

Before adding any new Composer or NPM dependency, explain why it is needed and ask for approval.

## 3. Development Principles

Follow these principles in all development tasks:

- Write clean, readable and maintainable code.
- Follow SOLID principles where appropriate.
- Follow Laravel conventions.
- Avoid unnecessary abstractions.
- Avoid overengineering.
- Keep controllers thin.
- Move complex business logic into dedicated service classes.
- Use dependency injection.
- Use PHP strict typing where appropriate.
- Use PHP enums for fixed and meaningful sets of values.
- Use database transactions for operations requiring atomicity.
- Avoid duplicated business logic.
- Prefer Laravel built-in features over custom implementations.
- Keep classes focused on a single responsibility.

Do not generate unnecessary files or classes.

Do not modify unrelated parts of the application.

## 4. Backend Architecture

Use Laravel's standard application structure with clear separation of responsibilities.

Recommended responsibilities:

- Controllers: Handle HTTP requests and responses.
- Models: Represent database entities and relationships.
- Form Requests: Validate incoming data.
- Services: Handle business logic.
- Policies: Handle authorization.
- Jobs: Handle asynchronous operations.
- Events and Listeners: Handle decoupled application events.
- Resources: Format API responses when APIs are needed.
- Enums: Represent fixed application states and types.

Avoid placing complex business logic directly inside controllers or Eloquent models.

Use service classes when business logic becomes complex or reusable.

Do not create a service class for every trivial operation.

## 5. Multi-Tenant Architecture

This is a multi-tenant SaaS platform.

Tenant isolation is a critical requirement.

Every tenant-owned resource must be associated with the correct tenant.

Rules:

- Never expose one tenant's data to another tenant.
- Always resolve the current tenant using a centralized tenant resolution mechanism.
- Never trust tenant IDs directly supplied by users.
- Apply tenant ownership checks to database queries.
- Ensure tenant isolation in background jobs.
- Ensure tenant isolation in caching.
- Ensure tenant isolation in file storage.
- Ensure tenant isolation in authorization.

The public QR menu must resolve the correct business using its configured domain, subdomain or public identifier.

Do not duplicate tenant resolution logic across controllers.

Before implementing the multitenancy system, establish and document the tenant identification strategy.

## 6. Database Standards

Use PostgreSQL as the primary database.

Rules:

- Use Laravel migrations for all schema changes.
- Use foreign keys where appropriate.
- Add indexes to frequently queried columns.
- Use unique constraints for business-critical identifiers.
- Use appropriate column types.
- Avoid unnecessary JSON columns when relational structures are more appropriate.
- Define Eloquent relationships explicitly.
- Avoid N+1 query problems.
- Use eager loading where appropriate.
- Never modify production data directly through untracked SQL changes.

Every schema modification must have a migration.

Use database transactions for multi-step operations that must succeed or fail together.

## 7. Frontend Architecture

Use Blade, Tailwind CSS and Alpine.js.

### Blade

- Use reusable Blade components.
- Use layouts for shared page structures.
- Keep business logic out of Blade templates.
- Use partials for reusable sections.
- Keep templates organized by feature.

### Tailwind CSS

- Use Tailwind utility classes.
- Maintain consistent spacing and typography.
- Build responsive interfaces.
- Avoid unnecessary custom CSS.
- Use reusable design patterns.

### Alpine.js

Use Alpine.js for lightweight interactions:

- Dropdowns.
- Modals.
- Mobile navigation.
- Tabs.
- Search interactions.
- Product detail modals.
- Language selectors.
- Small interactive UI components.

Do not use Alpine.js as a replacement for backend business logic.

Avoid unnecessary JavaScript.

## 8. Design and UI/UX Standards

The platform must have a modern, premium and professional SaaS design.

Design principles:

- Mobile-first responsive design.
- Clean and minimal interfaces.
- Consistent typography.
- Consistent spacing.
- Reusable UI components.
- Accessible color contrast.
- Clear visual hierarchy.
- Fast loading pages.
- Smooth but subtle interactions.

The marketing website and digital menu interfaces should have distinct design identities while sharing a consistent underlying design system.

### QR Menu Specific Requirements

QR menus are customer-facing pages and must prioritize mobile devices.

Every menu theme must support the same underlying menu data structure.

Themes should be presentation layers, not separate business logic implementations.

A menu theme may customize:

- Colors.
- Typography.
- Layout.
- Product card styles.
- Category navigation.
- Header and cover image.
- Product detail presentation.

Do not duplicate backend functionality for individual themes.

Do not hardcode business information or products inside theme templates.

All menu content must come from the database.

## 9. Redis and Caching

Use Redis for frequently accessed data where beneficial.

QR menu pages are high-priority caching candidates.

Rules:

- Use tenant-aware cache keys.
- Use menu-aware cache keys.
- Never share cached tenant data accidentally.
- Define appropriate cache expiration strategies.
- Invalidate relevant caches when menu data changes.
- Avoid caching user-specific or authorization-sensitive data globally.
- Avoid unnecessary database queries on public menu pages.

Cache keys should follow a consistent naming convention.

Example:

qrmenu:tenant:{tenant_id}:menu:{menu_id}

## 10. Security Standards

Security must be considered in every development task.

Rules:

- Validate all user input.
- Use Form Requests for complex validation.
- Use CSRF protection.
- Use Laravel authentication mechanisms.
- Apply authorization checks.
- Protect tenant-owned resources.
- Prevent mass assignment vulnerabilities.
- Never expose passwords, tokens or secrets.
- Never commit .env files.
- Never disable security middleware to bypass errors.
- Use secure file upload validation.
- Escape untrusted output.
- Avoid raw SQL unless necessary.
- Protect against SQL injection and XSS.

Never trust client-side tenant identifiers or authorization decisions.

## 11. Performance Standards

Performance is important because QR menus will be accessed frequently by mobile devices.

Rules:

- Prefer server-side rendering.
- Minimize JavaScript bundles.
- Optimize images.
- Use lazy loading for non-critical images.
- Avoid unnecessary database queries.
- Use eager loading.
- Use Redis caching where appropriate.
- Avoid loading unnecessary frontend dependencies.
- Keep public menu pages lightweight.
- Use pagination for large collections when appropriate.

## 12. Testing Standards

Use PHPUnit or the testing framework already configured in the project.

Write tests for:

- Authentication.
- Registration.
- Tenant isolation.
- Subscription-related business logic.
- Public QR menu access.
- Menu visibility.
- Menu category and product relationships.
- Cache invalidation.
- Authorization.

Every new critical feature should include appropriate tests.

Never claim that tests passed without actually running them.

## 13. Git and Code Quality

- Follow clear and descriptive naming conventions.
- Keep methods small and focused.
- Avoid commented-out production code.
- Avoid unnecessary TODO comments.
- Do not overwrite existing functionality without understanding its purpose.
- Never delete existing files without a clear reason.
- Never modify environment configuration without explaining the impact.

Do not commit changes unless explicitly requested.

## 14. Claude Development Workflow

Before implementing a feature:

1. Inspect the existing project structure.
2. Read the relevant files.
3. Understand the existing architecture.
4. Identify dependencies and potential side effects.
5. Propose an implementation plan for non-trivial tasks.
6. Implement the feature in small, logical steps.
7. Run relevant tests.
8. Check for regressions.
9. Summarize changed files and implementation decisions.

Do not assume that a feature already exists.

Do not generate the entire application in one step.

Do not create migrations, models, controllers and views blindly without understanding their relationships.

When requirements are ambiguous, ask for clarification instead of making major architectural assumptions.

When a task conflicts with these rules, explain the conflict before proceeding.

## 15. Current Development Priority

The initial development should focus on:

1. Project architecture and configuration.
2. Database foundation.
3. Multi-tenant infrastructure.
4. Public SaaS website.
5. Authentication.
6. Pricing and subscription foundation.
7. QR menu infrastructure.
8. Menu theme system.
9. Five initial menu themes.
10. Performance and security improvements.

Do not start implementing unrelated features without explicit instructions.

## Final Rule

Always prioritize maintainability, security, performance and architectural consistency.

Build this application as a commercial SaaS product that can support many businesses and grow without requiring a complete architectural rewrite.