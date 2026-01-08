# RT Employee Manager V2 - Project Constitution

## Core Philosophy

**"Boring is Better"** - This plugin prioritizes simplicity, reliability, and maintainability over cleverness or complexity. The original 1,070+ line plugin was the problem. This rewrite exists to solve that problem, not recreate it.

## Fundamental Principles

### 1. Simplicity Mandate
- **WordPress Native First**: Always use WordPress core functionality before building custom solutions
- **Minimal Dependencies**: Avoid new libraries, plugins, or external services unless absolutely necessary
- **Clear Over Clever**: Code should be immediately understandable by any PHP developer
- **One Responsibility**: Each class and function has a single, clear purpose

### 2. Architecture Constraints (Non-Negotiable)
- **Maximum 6 classes total** - Current architecture is complete
- **No custom database tables** - Use WordPress post meta and user meta exclusively
- **No complex abstractions** - Direct WordPress API calls, no wrapper layers
- **Native WordPress patterns** - Post types, meta boxes, user roles, capabilities

### 3. Code Quality Standards

#### Hard Limits (Never Exceed)
- Main plugin file: **200 lines maximum**
- Any single class: **300 lines maximum**
- Any single function: **50 lines maximum**
- Total plugin codebase: **2000 lines maximum**

#### Code Review Checklist
Before adding ANY code, verify:
- [ ] Does this make the plugin simpler?
- [ ] Could WordPress do this natively?
- [ ] Is this the minimum code to solve the problem?
- [ ] Would this pass a "boring code" review?
- [ ] Can any PHP developer understand this in 5 minutes?

### 4. Feature Scope (Immutable)

#### Core Features Only
The plugin does EXACTLY these things:
1. Admins create kunden (customers)
2. Kunden manage their employees
3. Generate PDFs with employee data
4. Email PDFs automatically
5. Download/resend PDFs
6. Austrian compliance (SVNR validation)

**Everything else is feature creep and must be rejected.**

#### Features to Never Add
- Custom debug logger classes (use `error_log()`)
- Complex menu customization (>50 lines)
- Custom capability matrices (use WordPress defaults)
- Rate limiting systems
- Multiple PDF generation fallbacks
- Custom authentication systems
- Status dashboards
- Audit logging tables
- Queue systems
- API endpoints
- Complex AJAX (forms submit normally)

### 5. Decision-Making Framework

When considering any change, ask:
1. ❓ Does this solve a problem the user actually reported?
2. ❓ Can this be done with existing WordPress functionality?
3. ❓ Will this make debugging harder?
4. ❓ Does this add more than 20 lines of code?
5. ❓ Is this "enterprise-level" thinking for a simple plugin?

**If ANY answer is YES → DON'T ADD IT**

### 6. Development Practices

#### Security Requirements
- Ownership checks on every employee action
- Nonces on all forms and AJAX requests
- Permission checks using WordPress capabilities
- Data sanitization for all user inputs
- Server-side validation only (no JavaScript dependencies)

#### Debugging Rules
- ❌ **NEVER rewrite from scratch** - Fix the specific issue
- ❌ **NEVER add logging classes** - Use `error_log()` only
- ❌ **NEVER add complexity to solve simple problems**
- ❌ **NEVER create new classes to fix old classes**
- ✅ **Keep the same simple architecture**

#### Testing Approach
- Manual testing is sufficient for this scale
- Test locally before any deployment
- Verify core workflows: registration, approval, employee management
- No complex test suites needed

### 7. WordPress Standards

#### Required Practices
- Use WordPress coding standards (PHP, HTML, CSS)
- Follow WordPress naming conventions
- Use WordPress hooks and filters appropriately
- Proper text domain usage for translations (`rt-employee-manager-v2`)
- German (Austria) as primary language

#### Data Storage
- Employee data: Post meta fields
- Company data: User meta fields
- No custom database tables
- Leverage WordPress core tables and indexes

### 8. Performance Expectations

#### Acceptable Approach
- Simple post queries only (no complex joins)
- No caching needed (WordPress handles this)
- Minimal JavaScript (basic form validation)
- Small footprint (~2000 lines total)

#### Performance Anti-Patterns
- Don't optimize prematurely
- Don't add caching layers
- Don't create custom query optimizations
- WordPress core is fast enough

### 9. Deployment Protocol

#### Development Workflow
1. **Local Development First** - Always test locally
2. **Test Thoroughly** - Verify all core workflows
3. **Deploy via FTP/SFTP** - Upload tested changes only
4. **Backup Before Deployment** - Always have rollback plan

#### Critical Rules
- ❌ **NO live edits** - Debug only, never modify files on live server
- ❌ **NO database changes** on live server
- ❌ **NO plugin activation/deactivation** on live
- ✅ **LOCAL DEVELOPMENT FIRST** - Always test locally

### 10. Maintenance Philosophy

#### When Bugs Occur
- Fix the specific issue, not the entire system
- Use existing architecture, don't refactor
- Add minimal code to solve the problem
- Test the fix, not the entire plugin

#### When Features Are Requested
- Evaluate against core feature list
- Reject if it's not in the 6 core features
- Suggest WordPress plugins if functionality exists elsewhere
- Keep plugin focused on employee management only

## Enforcement

This constitution applies to:
- All code changes
- All feature requests
- All architectural decisions
- All AI-assisted development
- All code reviews

**Any proposal that violates these principles must be rejected or modified to comply.**

## Amendment Process

This constitution can only be amended if:
1. A fundamental flaw in the principles is discovered
2. WordPress core changes require adaptation
3. Austrian compliance requirements change
4. The amendment maintains or improves simplicity

**Amendments must not increase complexity or add features beyond the core 6.**

---

**Remember: BORING CODE = WORKING CODE = PROFITABLE CODE**

