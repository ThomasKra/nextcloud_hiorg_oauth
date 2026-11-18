---
name: nextcloud:upgrade
description: Upgrade a Nextcloud app to a newer Nextcloud version by updating dependencies, checking for breaking changes, and applying necessary code changes. Creates changes in a separate branch dev/NC<target_version>.
permissions:
  - file_system:read
  - file_system:write
  - file_system:bash
  - run_typescript
  - ask_user_question
---

# Nextcloud App Version Upgrade

## Purpose

This skill automates the process of upgrading a Nextcloud app to support a newer Nextcloud version. It creates a new branch `dev/NC<target_version>`, handles dependency updates, breaking change detection, code modifications, and metadata updates.

## When to Use

- When upgrading your Nextcloud app to support a new Nextcloud version
- When ensuring compatibility with a specific Nextcloud release
- When updating the app's major version to match the target Nextcloud version

## Prerequisites

- Valid `composer.json` in the app root
- Valid `appinfo/info.xml` in the app root
- Composer installed and available in PATH
- Git initialized repository
- Clean working directory (no uncommitted changes)

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

4. **With custom branch name** (optional):
   ```
   nextcloud:upgrade --target 35 --branch dev/NC35-custom
   ```

## Configuration

### Environment Variables

- `NC_TARGET_VERSION`: Target Nextcloud version (overrides interactive prompt)
- `NC_DRY_RUN`: Set to "true" to preview changes without applying them
- `NC_BRANCH`: Custom branch name (overrides default `dev/NC<version>`)

## Implementation Steps

### Step 0: Pre-Flight Checks

**Before any changes, the skill performs validation:**

```bash
# Check we're in a git repository
if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
    echo "Error: Not in a git repository"
    exit 1
fi

# Check working directory is clean
if ! git diff --quiet && ! git diff --cached --quiet; then
    echo "Error: Working directory has uncommitted changes. Please commit or stash them first."
    git status --short
    exit 1
fi

# Check required files exist
if [ ! -f "appinfo/info.xml" ]; then
    echo "Error: appinfo/info.xml not found"
    exit 1
fi

if [ ! -f "composer.json" ]; then
    echo "Error: composer.json not found"
    exit 1
fi
```

### Step 1: Determine Current State

The skill first analyzes the current state of your app:

```bash
# Read current Nextcloud version from info.xml
CURRENT_MIN=$(grep -oP 'min-version="\K[^"]+' appinfo/info.xml)
CURRENT_MAX=$(grep -oP 'max-version="\K[^"]+' appinfo/info.xml)

# Read current OCP version from composer.json
CURRENT_OCP=$(grep -oP '"nextcloud/ocp": "\K[^"]+' composer.json)

# Read current app version
CURRENT_APP_VERSION=$(grep -oP '<version>\K[^<]+' appinfo/info.xml)
```

### Step 2: Prompt for Target Version

If not provided via environment variable or argument, the skill prompts:

```
Current Nextcloud version: 29 (min: 29, max: 29)
Current OCP version: ^28.0
Current app version: 20.0.0

Enter target Nextcloud version: [30-35]:
```

### Step 3: Validate Target Version

The skill validates that:
- Target version is numeric and >= current version
- Target version is supported (29-35 currently)
- PHP version requirements can be met

### Step 4: Create Target Branch

**Create a new branch for the upgrade:**

```bash
# Determine branch name
TARGET_BRANCH=${NC_BRANCH:-dev/NC$TARGET_VERSION}

# Check if branch already exists
git fetch origin $TARGET_BRANCH > /dev/null 2>&1
if git show-ref --quiet refs/heads/$TARGET_BRANCH > /dev/null 2>&1; then
    echo "Branch $TARGET_BRANCH already exists locally."
    read -p "Checkout existing branch? [y/N]: " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git checkout $TARGET_BRANCH
    else
        echo "Aborting."
        exit 1
    fi
else
    # Create and checkout new branch
    git checkout -b $TARGET_BRANCH
    echo "Created and checked out branch: $TARGET_BRANCH"
fi
```

This ensures all changes are made in an isolated branch (`dev/NC35`, `dev/NC34`, etc.).

### Step 5: Update composer.json

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

### Step 6: Run Composer Update

```bash
composer update nextcloud/ocp --with-dependencies
```

This updates the dependencies in `composer.lock`.

### Step 7: Check for Breaking Changes

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

### Step 8: Apply Code Changes

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

### Step 9: Update appinfo/info.xml

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

### Step 10: Commit Changes

After all changes are applied, the skill commits them to the branch:

```bash
# Determine commit message
if [ -z "$NC_DRY_RUN" ]; then
    git add -A
    git commit -m "feat(nc$TARGET_VERSION): Update to Nextcloud v$TARGET_VERSION compatibility

    Changes:
    - Updated nextcloud/ocp to ^$TARGET_VERSION.0
    - Applied NC32 breaking change fixes (if applicable)
    - Applied NC33 breaking change fixes (if applicable)
    - Applied NC34 breaking change fixes (if applicable)
    - Applied NC35 breaking change fixes (if applicable)
    - Updated app version to $TARGET_VERSION.0.0
    - Updated dependencies to min-version=\"$TARGET_VERSION\" max-version=\"$TARGET_VERSION\"

    Generated by Mistral Vibe.
    Co-Authored-By: Mistral Vibe <vibe@mistral.ai>"
    
    echo "Changes committed to branch: $TARGET_BRANCH"
fi
```

### Step 11: Summary and Next Steps

The skill provides a summary:

```
Upgrade to Nextcloud $TARGET_VERSION Complete!
=========================================

Branch: $TARGET_BRANCH
Files changed: X
Lines added: Y
Lines removed: Z

Changes applied:
✓ Updated composer.json
✓ Updated composer.lock
✓ Updated appinfo/info.xml
✓ Applied breaking change fixes for versions: [list]

Next steps:
1. Review changes: git diff origin/$(git branch --show-current)...$TARGET_BRANCH
2. Test the app with Nextcloud $TARGET_VERSION
3. Push the branch: git push origin $TARGET_BRANCH
4. Create a pull request
```

## Version Mapping Table

| Nextcloud Version | App Major Version | PHP Version | Branch Name | Notes |
|-------------------|-------------------|-------------|-------------|-------|
| 29 | 29 | 8.0-8.1 | dev/NC29 | Current baseline |
| 30 | 30 | 8.0-8.1 | dev/NC30 | No major breaking changes |
| 31 | 31 | 8.0-8.1 | dev/NC31 | No major breaking changes |
| 32 | 32 | 8.0-8.2 | dev/NC32 | Event system, jQuery, QueryBuilder |
| 33 | 33 | 8.2+ | dev/NC33 | Authentication flow |
| 34 | 34 | 8.2+ | dev/NC34 | Type safety, verification |
| 35 | 35 | 8.3+ | dev/NC35 | PHP 8.3, full cleanup |

## Safety Mechanisms

### Pre-Check Validation

Before making any changes, the skill performs:

1. **Backup creation**: Creates a backup of modified files in `.vibe/backup/`
2. **Git validation**: Ensures we're in a git repo with clean working directory
3. **File validation**: Verifies required files exist
4. **Version validation**: Ensures target version is supported
5. **Branch validation**: Checks if target branch already exists

### Dry Run Mode

When `--dry-run` or `NC_DRY_RUN=true` is set:
- All changes are previewed but not applied
- Shows exactly what would be changed
- Allows review before actual modification
- Does NOT create or checkout any branch
- Does NOT make any file changes

### Rollback Instructions

If issues occur after upgrade:
```bash
# Return to original branch
git checkout <original-branch>

# Or delete the upgrade branch
git branch -D dev/NC$TARGET_VERSION

# Or reset the branch to origin
git fetch origin
git reset --hard origin/<original-branch>
```

## Example: Upgrade from NC29 to NC35

```
$ vibe:nextcloud:upgrade

Nextcloud App Version Upgrade Skill
====================================

Analyzing current state...
✓ Git repository: clean
✓ Found appinfo/info.xml
✓ Found composer.json

Current state:
- App: hiorg_oauth
- Current NC version: 29 (min: 29, max: 29)
- Current OCP version: ^28.0
- Current app version: 20.0.0
- Current branch: dev/NC32

Enter target Nextcloud version: [30-35]: 35

Target branch will be: dev/NC35

Changes to apply:
─────────────────
1. Create branch: dev/NC35
2. [NC32] Update composer.json: nextcloud/ocp ^28.0 → ^35.0
3. [NC32] Apply jQuery method updates (settings.js)
4. [NC32] Apply QueryBuilder changes (SocialConnectDAO.php)
5. [NC32] Migrate event system (Application.php)
6. [NC33] Update authentication flow (LoginController.php)
7. [NC35] Remove @disregard comments
8. [NC35] Add type hints
9. [ALL] Update info.xml version: 20.0.0 → 35.0.0
10. [ALL] Update info.xml dependencies: min="29",max="29" → min="35",max="35"

Apply these changes to branch dev/NC35? [y/N]: y

Creating branch dev/NC35...
✓ Branch created and checked out

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

Committing changes...
✓ Changes committed to dev/NC35

Upgrade to Nextcloud 35 Complete!
================================

Branch: dev/NC35
Files changed: 8
Lines added: 45
Lines removed: 32

Next steps:
1. Review changes: git diff dev/NC32...dev/NC35
2. Test the app with Nextcloud 35
3. Push the branch: git push origin dev/NC35
4. Create a pull request
```

## Branching Strategy

### Default Branch Naming

The skill uses the pattern `dev/NC<TARGET_VERSION>` by default:
- Target 30 → `dev/NC30`
- Target 31 → `dev/NC31`
- Target 32 → `dev/NC32`
- Target 33 → `dev/NC33`
- Target 34 → `dev/NC34`
- Target 35 → `dev/NC35`

### Custom Branch Name

Use `--branch` or `NC_BRANCH` environment variable:
```bash
# Using argument
nextcloud:upgrade --target 35 --branch feature/nextcloud35-upgrade

# Using environment variable
export NC_BRANCH=feature/nc35
nextcloud:upgrade --target 35
```

### Branch Management

The skill handles branch operations safely:
1. **Existing branch**: Prompts before checking out
2. **New branch**: Creates and checks out automatically
3. **Origin check**: Verifies if branch exists on remote
4. **Clean state**: Ensures working directory is clean before creating branch

## Known Limitations

1. **App-Specific Code**: This skill handles common Nextcloud breaking changes. Your app may have custom code that needs manual attention.

2. **Third-Party Dependencies**: If your app uses third-party libraries, you may need to update them separately.

3. **Database Migrations**: If your app has database migrations, they may need updates for newer Nextcloud versions.

4. **API Changes**: If your app uses Nextcloud APIs that changed, you may need to update those calls manually.

5. **Testing**: This skill does not automatically test your app. Manual testing is required.

6. **Merge Conflicts**: If the target branch already exists with changes, you may need to resolve conflicts manually.

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

# Check current branch
git branch --show-current

# View changes before committing
git diff --cached
```

## Troubleshooting

### Common Issues and Solutions

**Issue: Working directory has uncommitted changes**
```
Solution:
1. Commit your changes: git commit -am "your message"
2. Or stash them: git stash
3. Then run the skill again
```

**Issue: Composer update fails**
```
Solution:
1. Check PHP version: php -v
2. Update PHP if needed
3. Try: composer update --with-all-dependencies
4. Check for conflicting packages in composer.json
```

**Issue: Branch already exists**
```
Solution:
1. Delete the branch: git branch -D dev/NC35
2. Or checkout and reset: git checkout dev/NC35 && git reset --hard
3. Then run the skill again
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
4. Review recent changes: git diff dev/NC32...dev/NC35
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
6. **Branch Strategy**: Use separate branches for each major version upgrade

## References

- [Nextcloud Developer Manual](https://docs.nextcloud.com/server/latest/developer_manual/)
- [Nextcloud Server Changelog](https://github.com/nextcloud/server/blob/master/CHANGELOG.md)
- [Nextcloud App API](https://docs.nextcloud.com/server/latest/developer_manual/app/devenv.html)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Git Branch Documentation](https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell)
