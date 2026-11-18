---
name: nextcloud:upgrade
description: Upgrade a Nextcloud app to a newer Nextcloud version by updating dependencies, checking for breaking changes, and applying necessary code changes.
permissions:
  - file_system:read
  - file_system:write
  - file_system:bash
  - run_typescript
  - ask_user_question
---

# Nextcloud App Version Upgrade

## Purpose

This skill automates the process of upgrading a Nextcloud app to support a newer Nextcloud version. It handles dependency updates, breaking change detection, code modifications, and metadata updates.

## When to Use

- When upgrading your Nextcloud app to support a new Nextcloud version
- When ensuring compatibility with a specific Nextcloud release
- When updating the app's major version to match the target Nextcloud version

## Prerequisites

- Valid `composer.json` in the app root
- Valid `appinfo/info.xml` in the app root
- Composer installed and available in PATH
- Git initialized repository

## Usage

### Invocation

The skill can be invoked in several ways:

1. **Interactive mode** (recommended):
   ```
   vibe:nextcloud:upgrade
   ```
   The skill will prompt you for the target Nextcloud version.

2. **With target version**:
   ```
   nextcloud:upgrade --target 35
   ```

3. **Dry run mode** (preview changes without applying):
   ```
   nextcloud:upgrade --target 35 --dry-run
   ```

## Configuration

### Environment Variables

- `NC_TARGET_VERSION`: Target Nextcloud version (overrides interactive prompt)
- `NC_DRY_RUN`: Set to "true" to preview changes without applying them

## Implementation Steps

### Step 1: Determine Current State

The skill first analyzes the current state of your app:

```bash
# Read current Nextcloud version from info.xml
CURRENT_MIN=$(grep -oP 'min-version="\K[^"]+' appinfo/info.xml)
CURRENT_MAX=$(grep -oP 'max-version="\K[^"]+' appinfo/info.xml)

# Read current OCP version from composer.json
CURRENT_OCP=$(grep -oP '"nextcloud/ocp": "\K[^"]+' composer.json)
```

### Step 2: Prompt for Target Version

If not provided via environment variable or argument, the skill prompts:

```
Current Nextcloud version: 29 (min: 29, max: 29)
Current OCP version: ^28.0

Enter target Nextcloud version: [30-35]:
```

### Step 3: Validate Target Version

The skill validates that:
- Target version is numeric and >= current version
- Target version is supported (29-35 currently)
- PHP version requirements can be met

### Step 4: Update composer.json

Updates the `nextcloud/ocp` dependency to match the target version:

**Before:**
```json
{
  "require-dev": {
    "nextcloud/ocp": "^28.0"
  }
}
```

**After (for NC35):**
```json
{
  "require-dev": {
    "nextcloud/ocp": "^35.0"
  }
}
```

### Step 5: Run Composer Update

```bash
composer update nextcloud/ocp --with-dependencies
```

This updates the dependencies in `composer.lock`.

### Step 6: Check for Breaking Changes

The skill checks for known breaking changes between the current and target versions:

#### Version-Specific Checks

**NC30-31: No breaking changes for most apps**
- Deprecation warnings may appear but should not break functionality

**NC32 Breaking Changes:**
- ✅ Legacy hook system removed
  - Old: `IUserManager::listen('\OC\User', 'preDelete', ...)`
  - New: `IEventDispatcher::addListener(BeforeUserDeletedEvent::class, ...)`
  
- ✅ jQuery method deprecations removed
  - `.success()` → `.done()`
  - `.error()` → `.fail()`
  - `.delegate()` → `.on()`

- ✅ QueryBuilder changes
  - `execute()` → `executeStatement()`

**NC33 Breaking Changes:**
- ✅ PHP 8.2+ required
- ✅ Authentication flow changes
  - Old: `completeLogin()` + `createSessionToken()`
  - New: `loginWithToken()`

**NC34 Breaking Changes:**
- ✅ Stricter type checking
- ✅ Interface verification needed

**NC35 Breaking Changes:**
- ✅ PHP 8.3+ required
- ✅ All deprecated methods removed
- ✅ Full type safety enforcement

### Step 7: Apply Code Changes

Based on the target version, the skill applies necessary transformations:

#### For NC32+ Targets:

**1. Event System Migration**
```php
// Search for:
IUserManager::listen(
// Replace with:
$dispatcher = \$this->getContainer()->get(IEventDispatcher::class);
$dispatcher->addListener(
```

**2. jQuery Method Updates**
```javascript
// In JS files:
.success(function(data) { ... }) → .done(function(data) { ... })
.error(function() { ... }) → .fail(function() { ... })
.delegate('.class', 'click', fn) → .on('click', '.class', fn)
```

**3. QueryBuilder Updates**
```php
// Search for:
if (method_exists(\$qb, 'executeStatement')) {
    \$qb->executeStatement();
} else {
    \$qb->execute();
}
// Replace with:
\$qb->executeStatement();
```

#### For NC33+ Targets:

**Authentication Flow Update**
```php
// Search for:
\$this->userSession->completeLogin(\$user, [...]);
\$this->userSession->createSessionToken(\$this->request, \$uid, \$loginName);
// Replace with:
\$this->userSession->loginWithToken(
    \$this->request,
    \$user,
    \$uid,
    [...]
);
```

#### For NC35+ Targets:

**1. Remove @disregard Comments**
```php
// Search for and remove all:
// @disregard P1007
// @disregard P1013
// @disregard P1014
```

**2. Add Type Hints**
```php
// Add to all methods:
public function methodName(
    TypeHint $param1,
    TypeHint $param2
): ReturnType {
    // ...
}
```

**3. PHP 8.3 Compatibility**
- Test with PHP 8.3
- Address any deprecation warnings
- Optionally use new PHP 8.3 features

### Step 8: Update appinfo/info.xml

Updates the app version and Nextcloud dependencies:

**Before:**
```xml
<info>
    <id>hiorg_oauth</id>
    <version>20.0.0</version>
    <dependencies>
        <nextcloud min-version="29" max-version="29" />
    </dependencies>
</info>
```

**After (for NC35):**
```xml
<info>
    <id>hiorg_oauth</id>
    <version>35.0.0</version>
    <dependencies>
        <nextcloud min-version="35" max-version="35" />
    </dependencies>
</info>
```

The major version is set to match the target Nextcloud version.

## Version Mapping Table

| Nextcloud Version | App Major Version | PHP Version | Notes |
|-------------------|-------------------|-------------|-------|
| 29 | 29 | 8.0-8.1 | Current baseline |
| 30 | 30 | 8.0-8.1 | No major breaking changes |
| 31 | 31 | 8.0-8.1 | No major breaking changes |
| 32 | 32 | 8.0-8.2 | Event system, jQuery, QueryBuilder |
| 33 | 33 | 8.2+ | Authentication flow |
| 34 | 34 | 8.2+ | Type safety, verification |
| 35 | 35 | 8.3+ | PHP 8.3, full cleanup |

## Safety Mechanisms

### Pre-Check Validation

Before making any changes, the skill performs:

1. **Backup creation**: Creates a backup of modified files
2. **Syntax check**: Validates PHP syntax after changes
3. **Dependency check**: Verifies composer dependencies are compatible
4. **Version validation**: Ensures target version is supported

### Dry Run Mode

When `--dry-run` or `NC_DRY_RUN=true` is set:
- All changes are previewed but not applied
- Shows exactly what would be changed
- Allows review before actual modification

### Rollback Instructions

If issues occur after upgrade:
```bash
# Revert all changes
git checkout .
composer install

# Or for specific files
git checkout appinfo/info.xml composer.json
composer install
```

## Example: Upgrade from NC29 to NC35

```
$ vibe:nextcloud:upgrade

Nextcloud App Upgrade Skill
============================

Analyzing current state...
✓ Found appinfo/info.xml
✓ Found composer.json

Current state:
- App: hiorg_oauth
- Current NC version: 29 (min: 29, max: 29)
- Current app version: 20.0.0
- Current OCP: ^28.0

Target Nextcloud version: 35

Changes to apply:
─────────────────
1. [NC32] Update composer.json: nextcloud/ocp ^28.0 → ^35.0
2. [NC32] Apply jQuery method updates (settings.js)
3. [NC32] Apply QueryBuilder changes (SocialConnectDAO.php)
4. [NC32] Migrate event system (Application.php)
5. [NC33] Update authentication flow (LoginController.php)
6. [NC35] Remove @disregard comments
7. [NC35] Add type hints
8. [ALL] Update info.xml version: 20.0.0 → 35.0.0
9. [ALL] Update info.xml dependencies: min="29",max="29" → min="35",max="35"

Apply these changes? [y/N]: y

Applying changes...
✓ Updated composer.json
✓ Running composer update...
✓ Applied jQuery updates
✓ Applied QueryBuilder updates
✓ Migrated event system
✓ Updated authentication flow
✓ Removed @disregard comments
✓ Added type hints
✓ Updated info.xml

Upgrade complete!

Summary:
- Files changed: 8
- Lines added: 45
- Lines removed: 32

Next steps:
1. Test the app with Nextcloud 35
2. Run PHP 8.3 compatibility checks
3. Review changes with git diff
4. Commit the changes
```

## Known Limitations

1. **App-Specific Code**: This skill handles common Nextcloud breaking changes. Your app may have custom code that needs manual attention.

2. **Third-Party Dependencies**: If your app uses third-party libraries, you may need to update them separately.

3. **Database Migrations**: If your app has database migrations, they may need updates for newer Nextcloud versions.

4. **API Changes**: If your app uses Nextcloud APIs that changed, you may need to update those calls manually.

5. **Testing**: This skill does not automatically test your app. Manual testing is required.

## Manual Intervention Points

### When Manual Code Changes Are Needed

1. **Custom OAuth providers**: If your app uses custom OAuth2 providers
2. **Special authentication flows**: Non-standard login mechanisms
3. **Complex database operations**: Custom queries or transactions
4. **Third-party service integrations**: External API connections
5. **Frontend frameworks**: If using Vue, React, or other frameworks

### How to Handle Manual Changes

```bash
# Search for deprecated methods
grep -r "@disregard" lib/

# Check for deprecated method calls
grep -r "completeLogin\|createSessionToken" lib/

# Verify type hints
grep -r "public function" lib/ | grep -v ": " | head -20
```

## Troubleshooting

### Common Issues and Solutions

**Issue: Composer update fails**
```
Solution:
1. Check PHP version: php -v
2. Update PHP if needed
3. Try: composer update --with-all-dependencies
4. Check for conflicting packages in composer.json
```

**Issue: Breaking changes not detected**
```
Solution:
1. Check Nextcloud changelog for your target version
2. Manually search for deprecated method usage
3. Check for @disregard comments in code
```

**Issue: App doesn't work after upgrade**
```
Solution:
1. Check Nextcloud log: tail -f data/nextcloud.log
2. Verify PHP version: php -v
3. Check all dependencies: composer status
4. Review recent changes: git diff
```

**Issue: Type errors in PHP 8.2/8.3**
```
Solution:
1. Run: php -l lib/Controller/LoginController.php
2. Add proper type hints
3. Fix return type declarations
4. Use strict_types where appropriate
```

## Best Practices

1. **Incremental Upgrades**: Upgrade one major version at a time (e.g., 29→30, then 30→31)
2. **Test Frequently**: Test after each version upgrade
3. **Check Logs**: Monitor Nextcloud logs for errors
4. **Backup**: Always backup before upgrading
5. **Review**: Use `git diff` to review all changes

## References

- [Nextcloud Developer Manual](https://docs.nextcloud.com/server/latest/developer_manual/)
- [Nextcloud Server Changelog](https://github.com/nextcloud/server/blob/master/CHANGELOG.md)
- [Nextcloud App API](https://docs.nextcloud.com/server/latest/developer_manual/app/devenv.html)
- [Composer Documentation](https://getcomposer.org/doc/)
