# Changelog

All notable changes to the VIT ID Authentication extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.9.6] - 22 September 2026

### Changed

- **Dependencies**: `auth0/auth0-php` updated from 8.19.0 to 9.2.0. The extension only uses the Authentication API (`login`, `exchange`, `getUser`, `logout`), which is unchanged in v9; the Management API is called directly over HTTP and is not affected.
- **Build**: Composer now resolves dependencies for PHP 8.2, the minimum supported version, regardless of the PHP used to build. The `php-http/discovery` Composer plugin is disabled so it cannot auto-add an HTTP client library; the SDK keeps using the Guzzle client that CiviCRM core already ships.

## [1.9.5] - 21 September 2026

### Fixed

- **CiviCRM 6.15+ compatibility**: The bundled Composer autoloader is now registered after CiviCRM's, so libraries core also ships (such as `psr/log`, v3 since CiviCRM 6.15) are loaded from core instead of from the extension's `vendor/`. The bundled `psr/log` v1 copy previously caused a fatal `Declaration of Psr\Log\AbstractLogger::emergency() must be compatible` error (HTTP 500) on the VIT ID login and callback pages. The explicit `psr/log` requirement was also dropped.
- **Logging**: Fixed an undefined variable warning in the "session created" log line after a successful login.

### Changed

- **Dependencies**: `auth0/auth0-php` updated from 8.17.0 to 8.19.0.

## [1.9.4] - 2025-11-27

### Fixed

- **Redirection**: Improved deep link detection for cases where CiviCRM renders the login form in-place without a redirect
  - Added fallback to use the current page URL (`REQUEST_URI`) as the destination if the `destination` query parameter is missing
  - Updated `Login.php` to correctly handle root-relative return URLs (starting with `/`)
  - Ensures users are redirected back to the protected page they were trying to access

## [1.9.3] - 2025-11-27

### Changed

- **Debugging**: Added comprehensive logging to diagnose deep link redirection issues
  - Added logs to `vitid_auth0.php` to trace `destination` parameter detection during login button generation
  - Added logs to `Login.php` to trace `destination` parameter reception during login initiation
  - Helps identify why deep links are not being preserved in some environments

## [1.9.2] - 2025-11-27

### Fixed

- **Redirection**: Fixed regression where deep links were lost because the login button didn't include the destination parameter
  - Updated login button generation to include `destination` query parameter
  - Updated `Login.php` to prioritize `destination` parameter over session context
  - Ensures users are returned to their original requested page after SSO login

## [1.9.1] - 2025-11-27

### Fixed

- **Redirection**: Fixed issue where users were always redirected to the dashboard after login
  - Now preserves the original destination URL (e.g., deep links to specific contacts)
  - Stores the return URL in the session before redirecting to Auth0
  - Validates the return URL to prevent open redirect vulnerabilities
  - Falls back to dashboard if no return URL is found or if it's invalid

## [1.9.0] - 2025-11-21

### Changed

- **Refactor**: Complete refactor of `Auth0Client` to use official Auth0 SDK methods
  - Replaced manual JWT verification and token exchange with SDK's `login()`, `exchange()`, and `getUser()` methods
  - Improved security and maintainability by relying on the maintained SDK
  - Restored `cookieSecret` configuration required by SDK
- **State Storage**: Implemented custom `DatabaseStore` for Auth0 SDK
  - Adapts SDK's `StoreInterface` to use CiviCRM database
  - Ensures OAuth state persists across redirects even with strict cookie policies
- **Database Schema**: Updated `civicrm_vitid_auth0_state` table
  - Changed schema to generic key-value store (`key`, `value`, `expire`) to support SDK's storage needs
  - **BREAKING CHANGE**: Requires database update (uninstall/reinstall extension or manual SQL update)
## [1.8.0] - 2025-11-21

### Added

- **CiviCRM 6.7.2 Angular Login Page Support**: Added support for Angular-based login page introduced in CiviCRM 6.7.2
  - Created new JavaScript file `js/vitid-auth0-login.js` that waits for Angular to render login form
  - JavaScript detects when Angular module `crmLogin` is ready and login form is rendered
  - Injects VIT ID login button dynamically after Angular renders the form
  - Maintains backward compatibility with template-based login pages (CiviCRM < 6.7.0)
  - Form visibility toggle functionality works with Angular-rendered forms
  - localStorage preference for showing/hiding standard login form is preserved

### Changed

- **Login Page Integration**: Updated `hook_civicrm_pageRun` to detect login page and inject JavaScript/CSS for Angular support
  - Detects `CRM_Standaloneusers_Page_Login` page class
  - Checks CiviCRM version to determine if Angular support is needed (6.7.0+)
  - Uses `CRM_Core_Resources` to inject JavaScript and CSS files
  - Passes login URL to JavaScript via `CRM.vars.vitidAuth0.loginUrl`
- **Template Hook**: Updated `hook_civicrm_alterContent` to skip execution on CiviCRM 6.7.0+
  - Added version check to prevent template manipulation on Angular-based login pages
  - Keeps template-based approach as fallback for older CiviCRM versions
- **CSS Selectors**: Enhanced CSS to support Angular-rendered form structure
  - Added selectors for `.standalone-auth-form` container
  - Added Angular-specific selectors for form fields without form ID
  - Maintains existing selectors for backward compatibility

### Technical Details

- JavaScript uses polling to wait for Angular module initialization
- Form detection targets `input[name="name"]` and `input[name="pass"]` attributes
- Button injection happens before the form element
- Form visibility is controlled via `.show-standard-form` class toggle
- Version detection uses `CRM_Utils_System::version()` to parse major.minor version
- Extension automatically selects appropriate method (template vs Angular) based on CiviCRM version

## [1.7.4] - 2025-11-21

### Fixed

- **CRITICAL FIX**: Fixed CiviCRM 6.7.2 compatibility with Redis session handler
  - Resolved session persistence issue where users were redirected back to login after successful authentication
  - Issue was caused by missing Redis PHP extension in CiviCRM Docker image
  - Added proper Redis session handler detection and logging
  - Removed unnecessary workarounds that were added during troubleshooting
  - Session cookies now properly set and persist across redirects
  - Both Auth0 login and regular login now work correctly with Redis sessions
- **SQL Installation Fix**: Fixed "DB Error: syntax error" during extension installation
  - `CRM_Core_DAO::executeQuery()` doesn't handle multiple SQL statements from a file
  - Changed install hook to execute each CREATE TABLE statement separately
  - Resolves SQL syntax error when installing extension

### Changed

- **Code Cleanup**: Removed unnecessary session initialization workarounds
  - Removed programmatic `ini_set('session.cookie_secure')` workaround (ini file works correctly)
  - Removed manual session ID generation code (Redis handles this automatically)
  - Removed manual cookie-setting code (Redis sets cookies automatically)
  - Simplified session verification logging
  - Code now relies on Redis session handler working correctly

### Technical Details

- Redis session handler requires PHP Redis extension to be installed
- Extension now properly detects Redis session handler and logs session state
- Session verification focuses on ensuring data is set correctly in `$_SESSION['CiviCRM']`
- All session management is handled by PHP's Redis session handler and CiviCRM's session wrapper

### Requirements

- **Docker**: Redis PHP extension must be installed in CiviCRM container
  - Create Dockerfile extending `civicrm/civicrm:6.7.2-php8.3`
  - Install Redis extension: `pecl install redis`
  - Enable extension: `docker-php-ext-enable redis`
- **PHP Configuration**: `zz-session.ini` must configure Redis session handler
  - `session.save_handler = redis`
  - `session.save_path = "tcp://redis:6379?database=1&prefix=PHPSESSID:&persistent=1&timeout=2.0&read_timeout=2.0"`
  - `session.cookie_secure = 1` (required for HTTPS)
  - `session.cookie_samesite = Lax`

## [1.7.3] - 2025-11-13

### Fixed

- **Syntax Error**: Fixed PHP parse error "unexpected token catch" in RoleMapper.php
  - Fixed missing closing brace for `if (empty($ufMatch))` block
  - The if block starting at line 422 was missing its closing brace before the outer catch block
  - Resolves parse error on line 775 that prevented extension from loading

## [1.7.2] - 2025-11-13

### Fixed

- **Syntax Error**: Fixed PHP parse error "unexpected token catch" in RoleMapper.php
  - Fixed incorrect indentation of `try` block on line 426
  - Fixed indentation of catch block content to properly align with try block
  - Resolves parse error that prevented extension from loading

## [1.7.1] - 2025-11-13

### Fixed

- **CRITICAL FIX**: Fixed "DB Error: already exists" caused by username unique constraint violation
  - `username` field in `civicrm_uf_match` has a global unique constraint (`UI_username`)
  - `User.create` was not setting `username`, causing it to default to empty string `''`
  - First user succeeded with empty username, subsequent users failed with unique constraint violation
  - Now explicitly sets `username = email` when creating users via `User.create`
  - Added fallback to update `username` after creation if `User.create` doesn't accept the parameter
  - Ensures existing UFMatch records also have `username` set correctly when reused
  - Resolves login failures for users after the first user (contact 9175 and others)

### Technical Details

- `User.create` now includes `->addValue('username', $email)` to set username to email
- After successful user creation, verifies `username` is set and updates if needed
- When finding existing UFMatch records, ensures `username` is set to email if missing
- Email is used as username since it should be unique per domain (and globally if emails are unique)
- All username updates are logged for debugging purposes

## [1.7.0] - 2025-11-13

### Changed

- **CRITICAL CHANGE**: Replaced manual UFMatch creation with User.create API
  - Now uses `User.create` API instead of `UFMatch.create` for user creation
  - CiviCRM automatically handles UFMatch creation and `uf_id` assignment
  - Removed all manual `uf_id` setting logic that was causing conflicts
  - Eliminates "DB Error: already exists" errors for new users
  - Follows CiviCRM Standalone best practices

### Fixed

- **User Creation**: Fixed login failures for new users where UFMatch creation failed
  - New users: Uses `User.create` API which automatically creates UFMatch and sets `uf_id` correctly
  - Recurrent users: Reuses existing UFMatch record found by query
  - Race conditions: Proper retry logic handles concurrent login attempts
  - No more manual `uf_id` conflicts or unique constraint violations

### Technical Details

- `User.create` API automatically:
  - Creates UFMatch record
  - Sets `uf_id` to match UFMatch.id (CiviCRM Standalone requirement)
  - Returns user with `id` field equal to `uf_id`
- Removed manual `uf_id` setting from UFMatch::create() calls
- Removed UPDATE statements that corrected `uf_id` after creation
- Email retrieval improved to get primary email from contact or use fallback
- Random secure password generated for SSO users (not used for authentication)

## [1.6.9] - 2025-11-13

### Fixed

- **Syntax Error**: Fixed PHP parse error in RoleMapper.php catch block
  - Fixed incorrect indentation in catch block that caused "unexpected token catch" error
  - Properly indented all code inside the catch block
  - Extension settings page now loads without syntax errors

## [1.6.8] - 2025-11-13

### Fixed

- **CRITICAL FIX**: Fixed "DB Error: already exists" error caused by uf_id unique constraint violation
  - Added check for existing UFMatch records with `uf_id = contactId` before creation
  - If `uf_id = contactId` exists and belongs to same contact, reuse that record
  - If `uf_id = contactId` exists but belongs to different contact, use safe temporary value (-999999)
  - Improved retry logic to query by both `contact_id` and `uf_id` when CREATE fails
  - Prevents unique constraint violations when contactId matches an existing uf_id value
  - Resolves login failures for users where contact_id equals an existing uf_id

### Technical Details

- Before creating UFMatch, now checks if any record exists with `uf_id = contactId`
- When conflict detected (uf_id exists for different contact), uses temporary value -999999
- After creation, immediately updates `uf_id` to match auto-increment `id` (CiviCRM Standalone requirement)
- Retry logic now searches by both `contact_id` and `uf_id = contactId` to find records created concurrently
- Validates that retry-found records belong to correct contact before using them

## [1.6.7] - 2025-11-13

### Fixed

- **CRITICAL FIX**: Fixed "DB Error: already exists" error during SSO login
  - Fixed race condition in UFMatch record creation that caused duplicate key violations
  - Improved UFMatch query to find records with NULL domain_id (handles orphaned records)
  - Added retry logic with proper error handling for concurrent login attempts
  - Changed role insertion to use INSERT IGNORE to prevent duplicate key errors
  - Enhanced error logging to identify which operation failed (UFMatch creation, role assignment, etc.)
  - Resolves login failures for first-time users and concurrent login scenarios

### Technical Details

- UFMatch query now searches by contact_id only (not filtering by domain_id) to catch orphaned records
- If UFMatch record found with NULL domain_id, it's updated to domain_id=1 instead of creating duplicate
- UFMatch creation wrapped in try-catch to handle race conditions gracefully
- Role insertion uses INSERT IGNORE to handle race conditions where DELETE didn't complete
- Improved error messages identify specific operation that failed for easier debugging

## [1.6.6] - 2025-01-16

### Fixed

- **Form Hiding**: Fixed form fields not being hidden on login page
  - Replaced form ID-specific CSS selectors with generic selectors targeting `.input-wrapper` and `.login-or-forgot` divs
  - Form detection now works regardless of form ID using dynamic JavaScript form finding
  - Added immediate JavaScript hiding of `.input-wrapper` and `.login-or-forgot` divs on page load to prevent flash
  - CSS uses generic `form:not(.show-standard-form) .input-wrapper` and `form:not(.show-standard-form) .login-or-forgot` selectors
  - Safe to use generic selectors since CSS is only injected on login page
  - Form fields now hidden immediately without any visible flash

### Technical Details

- Updated `vitid_auth0_civicrm_alterContent()` to use generic form selectors
- JavaScript finds form dynamically: `document.querySelector('form input[name="name"]')?.closest('form')` or `document.querySelector('form')`
- Added `.input-wrapper` and `.login-or-forgot` selectors to `vitid-auth0-login.css` as primary hiding method
- Kept existing form ID-specific selectors as fallback

## [1.6.5] - 2025-01-16

### Fixed

- **Fatal Error**: Fixed call to undefined method `addStyleCode()` preventing extension from loading
  - Removed non-existent `CRM_Core_Resources::addStyleCode()` method call
  - Moved inline CSS injection to `hook_civicrm_alterContent()` where it's injected at the start of content
  - CSS now injected before form HTML renders, ensuring form fields are hidden immediately
  - Extension now loads without fatal errors

## [1.6.4] - 2025-01-16

### Fixed

- **Syntax Error**: Fixed PHP parse error preventing extension installation
  - Fixed quote escaping issue in JavaScript string within PHP code
  - Changed JavaScript string from single-quoted PHP string to double-quoted PHP string
  - Updated JavaScript to use single quotes for strings, properly escaping double quotes
  - Extension now installs without parse errors

## [1.6.3] - 2025-01-16

### Fixed

- **Form Hiding - CSS-First Approach**: Completely rewrote form hiding mechanism for reliable hiding
  - Moved CSS injection to `<head>` section via `hook_civicrm_pageRun` using `addStyleCode()`
  - CSS now loads before form renders, eliminating any flash of visible form fields
  - Removed JavaScript-based inline style manipulation (CSS handles all visibility)
  - Simplified JavaScript to only toggle `.show-standard-form` class
  - Form fields (username, password, submit button, forgot password link) are hidden by default
  - Form container remains visible to keep logo and VIT ID button visible
  - CSS uses `:not(.show-standard-form)` selector for default hidden state
  - When toggle is activated, CSS shows fields via `.show-standard-form` class

### Technical Details

- CSS injected via `CRM_Core_Resources::singleton()->addStyleCode()` in `hook_civicrm_pageRun`
- CSS targets form fields directly: inputs, labels, submit buttons, forgot password links
- JavaScript simplified to class toggle only - no inline style manipulation
- Form container visibility preserved to maintain logo and VIT ID button display

## [1.6.2] - 2025-01-16

### Fixed

- **Form Hiding**: Fixed standard login form still being visible on page load

  - Added JavaScript to force hide form immediately using inline styles (`display: none`, `visibility: hidden`)
  - Form is now hidden before CSS loads, ensuring it never flashes visible
  - JavaScript ensures form class is properly removed on initial load
  - Form visibility state properly synchronized with localStorage preference

- **Button Width**: Fixed VIT ID button still being too wide
  - Set CSS `max-width: 400px` as default constraint
  - Added JavaScript to dynamically match button width to form container/inputs
  - Button width adjusts intelligently based on form visibility and container size
  - Added window resize listener to update button width responsively
  - Button now properly constrained and matches form input width when form is visible

### Changed

- **Toggle Icon**: Replaced gear icon with eye icon for better UX
  - Changed from `fa-cog` to `fa-eye` when form is hidden (showing password metaphor)
  - Icon changes to `fa-eye-slash` when form is visible (hiding password metaphor)
  - Icon toggles along with button text for consistent visual feedback
  - More intuitive icon choice that matches common password visibility patterns

## [1.6.1] - 2025-01-16

### Fixed

- **Button Width**: Fixed VIT ID button being too wide

  - Changed button width from 100% to auto with proper constraints
  - Button now matches form container width instead of full page width
  - Added CSS rules to constrain button width within form containers

- **Button Placement**: Fixed VIT ID button placement on login page

  - Button now appears below CiviCRM logo/image instead of before form
  - Added multiple regex patterns to detect logo elements for proper insertion
  - Falls back to form-based insertion if logo not found

- **Form Hiding**: Fixed standard login form not being hidden by default

  - Added aggressive CSS to hide entire form container by default
  - Form uses `display: none` and `visibility: hidden` when hidden
  - Form properly shows when toggle is activated with `.show-standard-form` class
  - Added support for table-based form layouts

- **Toggle Button Text**: Fixed toggle button text to reflect current state
  - Button now shows "Show standard login" when form is hidden (default state)
  - Button shows "Hide standard login" when form is visible
  - Text updates correctly on page load and toggle click

## [1.6.0] - 2025-01-16

### Added

- **Emergency Access Toggle**: Added toggle button to show/hide standard login form
  - Standard login form is now hidden by default for better UX
  - Toggle button with gear icon allows emergency access when Auth0 is unavailable
  - Form visibility preference is saved in localStorage
  - Button text changes dynamically ("Standard Login" / "Hide Standard Login")
  - Accessible with proper ARIA labels and keyboard navigation

### Changed

- **Improved Login Page UX**: Made VIT ID login the primary and only visible option
  - VIT ID button moved to top of login page (before form) for maximum prominence
  - Button styling enhanced: larger size (16px font), bolder weight (700), added shadow
  - Standard login form fields (username, password, login button, forgotten password link) hidden by default
  - Form labels and sections containing login fields are also hidden
  - CiviCRM logo remains visible at all times
  - Removed "or" divider since standard form is hidden by default
  - Improved button hover effects with subtle animation

### Technical Details

- Modified `vitid_auth0_civicrm_alterContent()` hook to inject VIT ID button before form and toggle button after form
- Added comprehensive CSS selectors to hide/show form elements based on `.show-standard-form` class
- JavaScript handles toggle functionality with localStorage persistence
- Toggle button styled subtly but remains discoverable for administrators
- Responsive design maintained for mobile devices

## [1.5.3] - 2025-01-16

### Changed

- **Button Layout**: Reordered elements and centered button
  - Moved "or" divider above the "Log in with VIT ID" button (was below)
  - Centered the VIT ID login button horizontally for better visual balance

## [1.5.2] - 2025-01-16

### Fixed

- **Button Placement and Styling**: Fixed button placement and width issues
  - Changed button insertion point to after form closes (outside form) instead of after form opens
  - Button now appears underneath the CiviCRM login form box as intended
  - Fixed button width to match form inputs (was too wide at 100% of page)
  - Reduced top margin from 20px to 15px for better spacing
  - Narrowed divider lines from 40% to 30% each for better proportions
  - Reduced divider margins from 20px to 15px

## [1.5.1] - 2025-01-16

### Fixed

- **CSS Loading Fix**: Fixed CSS file not loading on login page
  - Moved CSS loading from `hook_civicrm_alterContent()` to `hook_civicrm_pageRun()`
  - `alterContent` hook runs too late - CSS resources are already collected by that point
  - CSS now loads properly before page rendering, ensuring styles are applied

## [1.5.0] - 2025-01-16

### Changed

- **UI Enhancement**: Improved VIT ID login button styling and integration
  - Added dedicated CSS stylesheet (`css/vitid-auth0-login.css`) for better maintainability
  - Updated button to use institutional color `#ab192d` instead of generic purple
  - Removed all inline styles in favor of semantic CSS classes
  - Button now matches CiviCRM form styling patterns (spacing, borders, typography)
  - Full-width button to match form input width for better visual consistency
  - Added proper hover, focus, and active states for accessibility
  - Responsive design adjustments for mobile devices
  - CSS loads only on login pages for optimal performance

## [1.4.12] - 2025-11-12

### Changed

- **Improved Logging**: Added always-on logging for critical session operations
  - Session creation and verification now logged at INFO level (not just debug mode)
  - Session state before redirect is always logged to help diagnose login issues
  - Added session verification checks that log errors if session variables don't match expected values
  - Helps diagnose issues where login appears successful but user cannot access CiviCRM

## [1.4.11] - 2025-11-12

### Fixed

- **Bug Fix**: Role extraction from ID token when namespace URL differs from configured domain
  - Previously only checked roles in namespace URL generated from configured Auth0 domain
  - Now checks all namespace URLs in token payload for roles (handles cases where namespace in token differs from configured domain)
  - Also improved app_metadata extraction to check all possible namespace URLs
  - Fixes issue where roles in `https://example.auth0.com/roles` were not found when domain was configured as `harvard.eu.auth0.com`

## [1.4.10] - 2025-11-12

### Fixed

- **Bug Fix**: Support for Auth0 regional domains
  - Updated domain validation regex to support regional Auth0 domains (e.g., `*.eu.auth0.com`, `*.us.auth0.com`, `*.au.auth0.com`, `*.jp.auth0.com`)
  - Previously only accepted standard `*.auth0.com` format
  - Now correctly validates domains like `harvard.eu.auth0.com`
  - Updated placeholder text and error messages to reflect regional domain support

## [1.4.9] - 2025-01-15

### Changed

- **Code Quality**: Fixed SQL query consistency in RoleMapper
  - Replaced string concatenation for WHERE clause with parameterized query
  - Now uses parameterized queries consistently for all SQL operations
  - Improves security and code consistency
  - Prevents potential SQL injection vulnerabilities

## [1.4.8] - 2025-01-15

### Security

- **SECURITY FIX**: Added CSRF protection to form actions
  - Added CSRF token validation for all form submissions (delete/add mapping actions)
  - Uses CiviCRM's built-in form validation mechanism (`controller->validate()`)
  - Prevents cross-site request forgery attacks on role mapping operations
  - Invalid form submissions are now rejected with error message

## [1.4.7] - 2025-01-15

### Security

- **SECURITY FIX**: Prevented information disclosure in user-facing error messages
  - Previously, debug mode exposed full error messages (including file paths, stack traces) to end users
  - Now always shows generic error message to users regardless of debug mode setting
  - Full error details (including stack traces) are still logged for administrators
  - Debug mode now logs additional context but doesn't expose it to users
  - Prevents potential information leakage that could aid attackers

## [1.4.6] - 2025-01-15

### Security

- **SECURITY FIX**: Added comprehensive input validation and sanitization for form inputs
  - Added validation rules for Auth0 domain format (must match `*.auth0.com` pattern)
  - Added validation for Client ID format (alphanumeric, dash, underscore only)
  - Added validation for Client Secret minimum length
  - Added validation for Auth0 role name format (alphanumeric, dash, underscore only)
  - Added validation for CiviCRM role ID (must be positive integer)
  - Added validation for mapping ID in delete operations (must be positive integer)
  - All inputs are now validated before being saved to database
  - Prevents SQL injection and invalid data storage

## [1.4.5] - 2025-01-15

### Changed

- **Portability**: Made Auth0 namespace URLs configurable and portable
  - Replaced hardcoded `https://example.auth0.com/roles` and `https://example.auth0.com/app_metadata` URLs
  - Now dynamically generates namespace URLs from configured Auth0 domain
  - Added `getAuth0NamespaceUrl()` helper method to build namespace URLs
  - Extension is now portable to any Auth0 tenant without code changes
  - Still supports standard CiviCRM namespace (`https://civicrm.org/app_metadata`) as fallback

## [1.4.4] - 2025-01-15

### Changed

- **Code Quality**: Replaced hardcoded cookie secret salt with class constant
  - Replaced hardcoded string `'vitid_auth0_cookie'` with class constant `COOKIE_SECRET_SALT`
  - Improves code maintainability and makes the salt value explicit
  - Updated constant name to `COOKIE_SECRET_SALT` for clarity

## [1.4.3] - 2025-01-15

### Changed

- **Code Quality**: Replaced magic number with named constant for OAuth state expiry
  - Replaced hardcoded `900` seconds with class constant `OAUTH_STATE_EXPIRY_SECONDS`
  - Improves code readability and maintainability
  - Makes it easier to adjust expiry time in the future if needed

## [1.4.2] - 2025-01-15

### Security

- **SECURITY FIX**: Added explicit SSL verification to all curl calls
  - Previously, curl calls relied on system defaults for SSL verification
  - Now explicitly sets `CURLOPT_SSL_VERIFYPEER` to `true` and `CURLOPT_SSL_VERIFYHOST` to `2` for all Auth0 API calls
  - Applied to: Management API token requests, role fetching, and token exchange
  - Prevents man-in-the-middle attacks by ensuring SSL certificates are properly verified
  - Ensures secure communication with Auth0 endpoints

## [1.4.1] - 2025-01-15

### Security

- **SECURITY FIX**: Added input validation and sanitization for OAuth callback parameters
  - Previously, `$_GET` superglobal was directly assigned without validation
  - Now validates and sanitizes all OAuth callback parameters (`code`, `state`, `error`, `error_description`)
  - State parameter validated as hexadecimal string (matches our random_bytes generation)
  - Authorization code validated as URL-safe alphanumeric string
  - Error parameters sanitized to prevent injection attacks
  - Invalid parameter formats now throw exceptions instead of being passed through
  - Prevents potential injection attacks through malicious callback URLs

## [1.4.0] - 2025-01-15

### Security

- **CRITICAL SECURITY FIX**: Removed JWT signature verification bypass in debug mode
  - Previously, signature verification could be skipped when JWKS fetch failed if debug mode was enabled
  - This created a security risk where unsigned tokens could be accepted in production if debug mode was accidentally enabled
  - Signature verification is now always required regardless of debug mode setting
  - Failed JWKS fetches now properly throw exceptions instead of silently skipping verification
  - Improves security posture and prevents potential token forgery attacks

## [1.3.9] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed UFMatch creation failure by setting mandatory `uf_id` field during creation
  - `uf_id` is now set to `contactId` during UFMatch creation (satisfies mandatory requirement)
  - Immediately after creation, `uf_id` is updated to `UFMatch.id` (correct value for CiviCRM Standalone)
  - Resolves error: "Mandatory values missing from Api4 UFMatch::create: uf_id"
  - UFMatch records are now created successfully, allowing user authentication to proceed

### Changed

- UFMatch creation process
  - Now sets `uf_id = contactId` during initial creation
  - Then immediately updates `uf_id = UFMatch.id` after creation
  - Ensures creation succeeds while maintaining correct `uf_id` value

### Technical Details

- CiviCRM API4 requires `uf_id` to be set during UFMatch creation (cannot be NULL)
- We set it to `contactId` initially to satisfy the requirement, then correct it to `UFMatch.id`
- This two-step process ensures both creation success and correct final value

## [1.3.8] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed redirect loop by correcting `uf_id` assignment in UFMatch records
  - `uf_id` now correctly set to `UFMatch.id` instead of `contact_id`
  - CiviCRM Standalone requires `uf_id` to match the UFMatch record's auto-increment ID
  - Existing UFMatch records are automatically updated if `uf_id` doesn't match `UFMatch.id`
  - Resolves persistent redirect loop after successful authentication

### Changed

- UFMatch record creation and update logic
  - New UFMatch records: `uf_id` is set to `UFMatch.id` after creation
  - Existing UFMatch records: `uf_id` is validated and updated if it doesn't match `UFMatch.id`
  - Session `ufID` now correctly uses `UFMatch.id` instead of `contact_id`

### Added

- Enhanced debug logging for `uf_id` assignment
  - Logs UFMatch ID, Contact ID, and `uf_id` values
  - Logs when `uf_id` is updated for existing records
  - Helps diagnose authentication issues related to user ID mismatches

### Technical Details

- In CiviCRM Standalone, `uf_id` must match the UFMatch record's primary key (`id`)
- The working user pattern shows `uf_id = 1` matching `UFMatch.id = 1` (not `contact_id = 2`)
- Session `ufID` must match the `uf_id` value in the UFMatch table for proper authentication
- This ensures CiviCRM can correctly identify and authenticate the user after redirect

## [1.3.7] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed authentication failure by properly assigning roles to users
  - Roles are now assigned to users via `civicrm_user_role` table using `UFMatch.id` as `user_id`
  - Previously, roles were mapped but not assigned, causing authentication failures
  - CiviCRM Standalone requires roles in `civicrm_user_role` table for proper authentication
  - Resolves redirect loop and login failures after successful Auth0 authentication

### Added

- Automatic role assignment during user session creation
  - Roles are assigned based on Auth0 roles from JWT token
  - Existing roles are cleared and reassigned on each login (handles role changes)
  - Role assignment is verified after insertion
- Enhanced debug logging for role assignment
  - Logs UFMatch ID and Contact ID
  - Logs roles being assigned
  - Logs verification of successfully assigned roles

### Changed

- Improved UFMatch record handling
  - Ensures `domain_id` is set to 1 for both new and existing UFMatch records
  - Captures `UFMatch.id` for use in role assignment (not just `contact_id`)
- Role assignment process
  - Roles are now assigned via `civicrm_user_role` table
  - Uses `UFMatch.id` as `user_id` (not `contact_id`)
  - Handles both new users and existing users with role changes

### Technical Details

- CiviCRM Standalone checks `civicrm_user_role` table to determine user permissions
- The `user_id` in `civicrm_user_role` refers to `UFMatch.id`, not `contact_id`
- Role assignment must happen after UFMatch record is created/retrieved
- Roles are reassigned on each login to handle changes in Auth0 role assignments

## [1.3.6] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed redirect loop by improving session handling
  - Removed `session_write_close()` call - let PHP handle session closing automatically
  - Closing session manually was preventing CiviCRM from reading it on next request
  - Changed redirect URL to `civicrm/dashboard` without `reset=1` parameter
  - Session now persists properly across redirect

### Added

- Enhanced debug logging for session structure
  - Logs all session keys for debugging
  - Logs CiviCRM session structure (`$_SESSION['CiviCRM']`) if it exists
  - Helps diagnose session persistence issues

### Changed

- Improved session handling before redirect
  - Removed manual session closing (`session_write_close()`)
  - PHP automatically handles session closing when script ends
  - Ensures CiviCRM can read session on next request
- Redirect URL changes
  - Changed from `civicrm` to `civicrm/dashboard` (standard post-login destination)
  - Removed `reset=1` parameter which may cause redirect loops

### Technical Details

- CiviCRM stores session data in `$_SESSION['CiviCRM']` structure
- `CRM_Core_Session::singleton()->set()` properly handles this structure
- Manual session closing can interfere with CiviCRM's session management
- Session must remain open for CiviCRM to read authentication state after redirect

## [1.3.5] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed redirect loop after successful authentication
  - Added `exit` after redirect to prevent further script execution
  - Prevents `parent::run()` from executing after redirect, which was causing redirect loop
  - Ensures session is saved before redirecting using `storeSessionObjects()`
  - Resolves "ERR_TOO_MANY_REDIRECTS" error after Auth0 callback

### Added

- Explicit session save before redirect
  - Calls `storeSessionObjects()` to ensure session data is persisted
  - Prevents session loss during redirect
- Enhanced debug logging for redirect process
  - Logs session state (userID, ufID) before redirect
  - Logs redirect URL for debugging

### Changed

- Improved redirect handling in callback
  - Both success and error redirects now properly exit
  - Prevents any code execution after redirect
  - `parent::run()` moved to end with safety comment

### Technical Details

- `CRM_Utils_System::redirect()` sets headers but doesn't exit script
- Added explicit `exit` after all redirects to prevent further execution
- Session must be explicitly saved before redirect to ensure persistence
- Redirect loop was caused by `parent::run()` executing after redirect

## [1.3.4] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed JWT signature verification failure
  - Corrected JWK to PEM conversion to properly handle unsigned modulus/exponent
  - Fixed DER encoding structure to use proper RSA public key format
  - Added 0x00 padding for modulus/exponent when first byte >= 0x80 (DER requirement)
  - Proper RSA algorithm OID and BIT STRING encoding in DER structure
  - Resolves "JWT signature verification failed" error

### Added

- PEM key validation before signature verification
  - Validates PEM key format using `openssl_pkey_get_public()` before verification
  - Provides clear error messages if key format is invalid
- Enhanced debug logging for signature verification process
  - Logs PEM key preview (masked for security)
  - Logs JWT header and payload (base64)
  - Logs data and signature lengths
  - Logs signature hex preview (first 32 bytes)
  - Logs OpenSSL verify result with detailed explanation
  - Collects all OpenSSL errors (not just the first one)

### Changed

- Improved error handling in signature verification
  - More detailed error messages for debugging
  - Better OpenSSL error collection and reporting
- PHP 8.0+ compatibility fix
  - Conditional `openssl_free_key()` call (deprecated in PHP 8.0+)

### Technical Details

- JWK to PEM conversion now uses correct DER structure:
  - SEQUENCE { algorithm OID (RSA), NULL }
  - BIT STRING { SEQUENCE { modulus, exponent } }
- Modulus and exponent are properly formatted as unsigned integers
- Signature verification now validates PEM key before attempting verification

## [1.3.3] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed SQL syntax error during extension installation
  - Changed from reading SQL file to executing CREATE TABLE statements directly in PHP
  - `CRM_Core_DAO::executeQuery()` doesn't handle multiple SQL statements from a file
  - Each CREATE TABLE statement now executed separately for reliability
  - Resolves "DB Error: syntax error" during installation

### Technical Details

- Install hook now executes CREATE TABLE statements directly instead of parsing SQL file
- More reliable and avoids SQL parsing issues
- SQL file (`auto_install.sql`) still maintained for reference

## [1.3.2] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Fixed "Invalid state parameter" error by replacing session-based state storage with database-backed storage
  - OAuth state, nonce, and code_verifier now stored in database table instead of session
  - Resolves session persistence issues across Auth0 redirects
  - State persists reliably regardless of session configuration
  - States automatically expire after 15 minutes
  - States are deleted after validation (one-time use)

### Added

- New database table: `civicrm_vitid_auth0_state` for OAuth state storage
  - Fields: `state` (primary key), `nonce`, `code_verifier`, `created_at`
  - Indexed on `created_at` for efficient cleanup
  - Automatically created on extension install/upgrade
  - Automatically dropped on extension uninstall
- Helper methods in `Auth0Client.php`:
  - `storeOAuthState()` - Stores OAuth state in database
  - `retrieveOAuthState()` - Retrieves OAuth state from database
  - `deleteOAuthState()` - Deletes state after validation
  - `cleanupExpiredStates()` - Removes expired states (older than 15 minutes)

### Changed

- **OAuth state storage moved from session to database**
  - `getLoginUrl()` now stores state in database instead of session
  - `handleCallback()` now retrieves state from database instead of session
  - More reliable for OAuth flows with external redirects
  - Works regardless of session configuration or cookie settings

### Technical Details

- State table uses `CREATE TABLE IF NOT EXISTS` for safe upgrades
- Automatic cleanup of expired states on each login attempt and callback
- States expire after 15 minutes for security
- States are single-use (deleted immediately after validation)
- Upgrade hook ensures table exists for existing installations

### Migration Notes

- **Database change**: New table `civicrm_vitid_auth0_state` will be created
- **No data migration needed**: Old session-based states are not migrated (they expire quickly anyway)
- **Upgrade path**: Table automatically created when extension is enabled/upgraded
- **Uninstall**: Table automatically dropped when extension is uninstalled

## [1.3.1] - 2025-01-15

### Improved

- **Enhanced error handling and debugging**
  - Added full exception logging with stack traces in callback handler
  - Step-by-step logging to identify exactly where authentication fails (6 steps)
  - Shows actual error messages to users when debug mode is enabled
  - Improved error messages for JWT signature verification failures
  - Better error context for JWKS fetch failures (includes network errors)
  - More detailed error messages for OpenSSL signature verification

### Added

- Step-by-step debug logging in `Callback.php`:
  - Step 1: Auth0 client initialization
  - Step 2: Callback parameter reception
  - Step 3: Auth0 callback processing
  - Step 4: User validation
  - Step 5: Role mapping
  - Step 6: Session creation
- Enhanced JWT signature verification error handling:
  - Network timeout configuration (10 seconds)
  - Detailed error messages for each failure point
  - Lists available key IDs when key not found
  - OpenSSL error reporting
  - Better error messages for JWK to PEM conversion failures

### Changed

- Error messages now show actual exception details when debug mode is enabled
- Generic error messages still shown in production (when debug disabled)
- JWT signature verification now skips only in debug mode if JWKS fetch fails (was always skipping)

### Technical Details

- Exception logging includes: message, file, line number, and full stack trace
- JWKS fetch includes 10-second timeout and proper error context
- All error logging happens regardless of debug mode (for troubleshooting)
- User-facing error messages are conditional based on debug mode

## [1.3.0] - 2025-01-15

### Fixed

- **CRITICAL FIX**: Removed `setUserContext()` call that caused fatal error in CiviCRM Standalone

  - `CRM_Utils_System_Standalone` doesn't have `setUserContext()` method
  - Session variables (`userID`, `ufID`) are sufficient for authentication in Standalone
  - Fixes fatal error: "Call to undefined method CRM_Utils_System_Standalone::setUserContext()"

- **CRITICAL FIX**: Replaced all hardcoded callback URLs with dynamic URL generation

  - Removed hardcoded `https://example.org/civicrm/vitid-auth0/callback` URLs
  - Now uses `CRM_Utils_System::url()` for dynamic callback URL generation
  - Enables multi-environment deployment (dev/prod) without code changes
  - Updated in constructor, `getLoginUrl()`, and `exchangeCodeForTokens()` methods

- **SECURITY FIX**: Replaced manual SQL escaping with parameterized queries

  - Fixed SQL injection risk in `hasValidRoles()` and `getCiviCRMRoles()` methods
  - Now uses `CRM_Core_DAO::executeQuery()` with proper parameterized placeholders
  - Follows CiviCRM security best practices

- **SECURITY FIX**: Implemented proper JWT signature verification
  - Added `verifyJWTSignature()` method that fetches JWKS from Auth0
  - Verifies RS256 signatures using OpenSSL
  - Converts JWK to PEM format for signature verification
  - Previously skipped signature verification (security risk)

### Changed

- **Debug logging is now conditional** via settings page checkbox
  - Added `vitid_auth0_debug` setting (Boolean, default: false)
  - All debug logging wrapped with conditional checks
  - Debug logging disabled by default for production performance
  - Can be enabled via Settings page for troubleshooting

### Added

- New setting: `vitid_auth0_debug` - Enable/disable debug logging
- Helper method `getCallbackUrl()` in `Auth0Client.php` for dynamic URL generation
- Helper methods `isDebugEnabled()` and `debugLog()` in both `Auth0Client.php` and `RoleMapper.php`
- JWT signature verification methods:
  - `verifyJWTSignature()` - Verifies JWT signature using Auth0 JWKS
  - `convertJWKToPEM()` - Converts JSON Web Key to PEM format
  - `encodeLength()` - Helper for DER encoding

### Improved

- Better code organization with helper methods
- Improved security with proper SQL parameterization
- Production-ready with conditional debug logging
- Multi-environment support with dynamic URLs

### Technical Details

- JWT signature verification fetches keys from `https://{domain}/.well-known/jwks.json`
- Signature verification uses OpenSSL with RS256 algorithm
- If JWKS fetch fails, logs warning but doesn't fail (for development flexibility)
- All SQL queries now use parameterized placeholders (%1, %2, etc.)
- Debug logging checks setting before logging to improve performance

### Migration Notes

- **No database changes required** - upgrade is seamless
- **Settings**: New debug logging checkbox appears in Settings page (disabled by default)
- **Auth0 Configuration**: No changes needed - callback URLs are now generated dynamically
- **Breaking Changes**: None - all changes are backward compatible

### Testing Recommendations

- Test login flow end-to-end after upgrade
- Verify callback URLs work correctly in your environment
- Test with debug logging enabled/disabled
- Verify role mapping validation still works correctly
- Check CiviCRM logs for any JWKS fetch warnings

## [1.2.9] - 2025-11-05

### Added

- **Comprehensive debug logging** for Auth0 role extraction and validation
- Debug logging in `Auth0Client.php` to show complete ID token payload structure
- Debug logging for roles extraction from both standard and namespaced claims
- Debug logging in `RoleMapper.php` for user validation process
- Debug logging for role mapping SQL queries and database state
- Automatic fallback to namespaced roles claim (`https://example.auth0.com/roles`)

### Improved

- Enhanced error reporting for authentication flow with detailed context
- Role extraction now checks both `roles` and `https://example.auth0.com/roles` claims
- Database query logging shows exact SQL and all role mappings in database
- Session data structure logging for troubleshooting

### Technical Details

- Logs show ID token payload keys and structure (with sensitive data masked)
- Logs show roles extraction attempts from multiple claim locations
- Logs show incoming Auth0 roles, SQL query execution, and query results
- Logs show all role mappings in database for comparison
- Helps identify mismatches between Auth0 roles and configured mappings

### Purpose

- Diagnostic version to troubleshoot role mapping validation failures
- Enables identification of role claim location issues in Auth0 tokens
- Helps verify Auth0 Action configuration is working correctly
- Allows comparison of incoming roles with database mappings

## [1.2.8] - 2025-05-11

### Fixed

- **CRITICAL FIX**: Resolved "Invalid state" error by implementing manual OAuth 2.0 token exchange
- Bypassed Auth0 SDK's session-dependent state validation that was incompatible with CiviCRM's session handling
- Fixed session storage compatibility issue where Auth0 SDK couldn't access state parameters stored in CiviCRM's session structure

### Changed

- **Implemented manual token exchange**: Direct OAuth 2.0 token endpoint call instead of using Auth0 SDK's `exchange()` method
- **Implemented manual JWT validation**: Custom ID token validation with proper OIDC claim verification
- Removed dependency on Auth0 SDK for authentication flow (SDK still used for Management API only)

### Added

- `exchangeCodeForTokens()` method: Manually exchanges authorization code for access and ID tokens
- `decodeAndValidateIdToken()` method: Decodes and validates JWT ID tokens with proper OIDC compliance
- `base64UrlDecode()` helper method: Decodes Base64 URL-encoded JWT parts
- Support for custom namespace in app_metadata: `https://example.auth0.com/app_metadata`

### Security

All OAuth 2.0 and OpenID Connect security best practices maintained:

- ✅ PKCE (Proof Key for Code Exchange) with SHA-256 challenge
- ✅ State parameter validation for CSRF protection
- ✅ Nonce validation for replay protection
- ✅ JWT signature and claims validation (issuer, audience, expiration, issued-at time)
- ✅ Client secret authentication
- ✅ HTTPS-only communications

### Technical Details

- Token exchange via direct HTTPS POST to Auth0's `/oauth/token` endpoint
- ID token validation checks: issuer (iss), audience (aud), expiration (exp), issued-at (iat), nonce
- All OAuth 2.0 flows implemented according to RFC 6749 and RFC 7636 (PKCE)
- OIDC compliance per OpenID Connect Core 1.0 specification
- Compatible with CiviCRM Standalone's session handling architecture

### Root Cause Analysis

v1.2.7 debug logs revealed:

1. Session ID remained constant between login and callback ✅
2. State parameter was stored and retrieved correctly ✅
3. Our validation passed successfully ✅
4. Auth0 SDK's internal validation failed ❌

The Auth0 SDK stores and retrieves OAuth state from PHP's native `$_SESSION` superglobal, but CiviCRM stores session data in a nested structure under `$_SESSION['CiviCRM']`. While we could retrieve data using CiviCRM's session API, the Auth0 SDK couldn't access it directly, causing the "Invalid state" error after our validation succeeded.

## [1.2.7] - 2025-05-11

### Added

- **Extensive debug logging** for OAuth state parameter and session tracking
- Log session ID at login initiation and callback to track session persistence
- Log state/nonce/code_verifier generation and storage
- Log state parameter retrieval and validation during callback
- Log all session keys and vitid*auth0*\* specific keys for debugging
- Detailed error messages showing expected vs received state values

### Purpose

- **Debug version** to diagnose "Invalid state" error during Auth0 callback
- Helps identify session persistence issues in CiviCRM Standalone
- Logs will reveal if session data is lost between login initiation and callback
- Enables analysis of whether session ID changes between requests
- Once issue is identified, will guide implementation of proper fix (likely cookie-based state storage)

### Technical Details

- Added logging in `getLoginUrl()` method before and after session storage
- Added logging in `handleCallback()` method to track state retrieval
- Logs include session ID, generated values, stored values, and session contents
- All logging uses `\Civi::log()->debug()` for detailed tracking

## [1.2.6] - 2025-05-11

### Fixed

- **Template loading issue**: Fixed error handling to display specific error messages on login page instead of using custom error template
- Resolved "Unable to load 'file:CRM/VitidAuth0/Page/Error.tpl'" error in CiviCRM Standalone
- Error page now redirects to login page with CiviCRM's native status message system

### Changed

- Updated `CRM_VitidAuth0_Page_Callback` to use `CRM_Core_Session::setStatus()` for error display
- Updated `CRM_VitidAuth0_Page_Error` to redirect to login page instead of rendering template
- Removed dependency on `Error.tpl` Smarty template

### Improved

- Better user experience: errors now displayed prominently on familiar login page
- Specific validation errors are preserved and shown (e.g., "Your VIT ID account is not linked to a CiviCRM contact", "You don't have the required permissions")
- Consistent with standard CiviCRM error handling patterns

### Technical Details

- Errors displayed using `CRM_Core_Session::setStatus($message, 'VIT ID Authentication Error', 'error')`
- All error redirects now point to `civicrm/login` instead of `civicrm/vitid-auth0/error`
- Maintains backward compatibility with existing error detection in callback flow

## [1.2.5] - 2025-11-05

### Fixed

- **CRITICAL FIX**: Resolved URL hashing issue by bypassing Auth0 SDK's problematic login() method
- Manually construct OAuth authorization URL to maintain complete control over redirect_uri parameter
- Fixed "Oops!, something went wrong" error from Auth0 caused by hashed redirect_uri

### Root Cause Identified

After extensive debugging with v1.2.4, logs revealed that:

- Our hardcoded callback URL was correctly set: ✅
- SdkConfiguration contained the correct URL: ✅
- Auth0 SDK's `login()` method transformed it to a hash: ❌

The Auth0 PHP SDK's `login()` method was internally calling CiviCRM's URL system, which was hashing the redirect_uri parameter.

### Solution Implemented

- Bypassed Auth0 SDK's `login()` method entirely
- Manually construct the OAuth 2.0 authorization URL with all required parameters
- Implement proper PKCE (Proof Key for Code Exchange) flow manually
- Store code_verifier, state, and nonce in session for callback verification
- Ensures redirect_uri remains as proper URL throughout the entire OAuth flow

### Technical Details

- Authorization URL now built directly with `http_build_query()`
- PKCE code verifier and challenge generated manually using SHA256
- All OAuth parameters (response_type, client_id, redirect_uri, scope, state, nonce, code_challenge) explicitly set
- No interaction with CiviCRM's internal URL transformation system
- Callback URL hardcoded to production domain: `https://example.org/civicrm/vitid-auth0/callback`

## [1.2.4] - 2025-11-05

### Status: DEBUG VERSION - CORRECTED

This version corrects the hardcoded URL to use the actual production domain and removes misleading metadata.

### Changed

- **Corrected hardcoded callback URL** from `https://civicrm.example/civicrm/vitid-auth0/callback` to `https://example.org/civicrm/vitid-auth0/callback`
- Removed misleading `<urls>` section from info.xml that contained incorrect domain

### Purpose

Same as v1.2.3 but with the correct production URL. This version will help identify:

- Whether the URL is correct when passed to Auth0 SDK
- Whether the URL is correct in the SdkConfiguration object
- What URL the Auth0 SDK's login() method actually returns
- Where in the chain the hashing transformation occurs

### Instructions

1. Deploy this version
2. Clear all caches
3. Try to log in
4. Check CiviCRM logs for the three debug messages starting with "VIT ID Auth0Client"
5. Report findings to determine root cause

## [1.2.3] - 2025-11-05

### Status: DEBUG VERSION

This version adds extensive logging to trace where URL hashing occurs.

### Added

- Debug logging in constructor to show callback URL being set
- Debug logging to show what SdkConfiguration returns for redirectUri
- Debug logging in getLoginUrl() to show the full URL returned by Auth0 SDK

### Purpose

After v1.2.2 confirmed that even hardcoded URLs get hashed, this version will help identify:

- Whether the URL is correct when passed to Auth0 SDK
- Whether the URL is correct in the SdkConfiguration object
- What URL the Auth0 SDK's login() method actually returns
- Where in the chain the hashing transformation occurs

### Instructions

1. Deploy this version
2. Clear all caches
3. Try to log in
4. Check CiviCRM logs for the three debug messages starting with "VIT ID Auth0Client"
5. Report findings to determine root cause

## [1.2.2] - 2025-11-05

### Status: TESTING VERSION

This is a diagnostic version to isolate where URL hashing is occurring.

### Changed

- **TEMPORARY TEST**: Hardcoded callback URL to `https://example.org/civicrm/vitid-auth0/callback`
- This version tests whether the URL hashing occurs even with a hardcoded string
- If hashing still occurs with hardcoded URL, the issue is in the Auth0 SDK or another layer
- If hashing does NOT occur, the issue was with our base URL detection methods

### Technical Details

- Bypassed all URL detection logic to use literal hardcoded string
- This isolates whether the problem is in URL construction or elsewhere in the OAuth flow
- DO NOT USE IN PRODUCTION - this is for diagnostic purposes only

## [1.2.1] - 2025-11-05

### Fixed

- **CRITICAL FIX**: Fixed callback URL generation for CiviCRM Standalone installations
- Resolved issue where `CRM_Utils_System::url()` was generating a hash instead of a proper URL
- Changed redirect_uri from hash (e.g., `224760e7aced5b4914c33c287b6c0522`) to full URL (e.g., `http://localhost:7979/civicrm/vitid-auth0/callback`)
- Fixed Auth0 "Oops!, something went wrong" error caused by invalid redirect_uri parameter
- Also fixed logout return URL construction to use proper absolute URLs

### Technical Details

- Manually construct callback URL using `CIVICRM_UF_BASEURL` constant or `\Civi::paths()->getUrl('[civicrm.root]/')`
- Bypass CiviCRM's URL generation system which has issues with absolute URLs in Standalone mode
- Applied same fix to both login callback URL and logout return URL
- Ensures Auth0 OAuth flow receives proper, fully-qualified redirect URIs

## [1.2.0] - 2025-11-05

### Fixed

- **CRITICAL FIX**: Discovered and implemented CiviCRM Standalone's actual public access mechanism
- Changed `<is_public>true</is_public>` to `<access_arguments>*always allow*</access_arguments>` in XML menu configuration
- This is the **correct syntax** that CiviCRM Standalone uses for public routes (verified from core source code)
- Fixed 403 "Please sign in" error when unauthenticated users clicked "Log in with VIT ID" button

### Technical Details

- Analyzed CiviCRM Standalone's own login page source code from GitHub repository
- Found that Standalone uses special `*always allow*` syntax in `<access_arguments>` for public routes
- This is used in core files like `standaloneusers.xml` for `/civicrm/login`, `/civicrm/mfa/totp`, etc.
- The `<is_public>true</is_public>` flag and `hook_civicrm_checkAccess()` approaches did not work because they're not the mechanism Standalone uses
- Routes now properly accessible: `/civicrm/vitid-auth0/login`, `/civicrm/vitid-auth0/callback`, `/civicrm/vitid-auth0/error`

### Supersedes

- Version 1.1.8: Used `<is_public>true</is_public>` (incorrect for Standalone)
- Version 1.1.9: Added `hook_civicrm_checkAccess()` (not effective for Standalone)
- Version 1.2.0: Uses correct `*always allow*` syntax verified from CiviCRM core source

## [1.1.9] - 2025-11-05

### Status: DEPRECATED

This version attempted to use `hook_civicrm_checkAccess()` to bypass permission checks, but this approach was not effective for CiviCRM Standalone. Use v1.2.0 instead.

### Fixed

- **CiviCRM Standalone permission issue**: Added `hook_civicrm_checkAccess` implementation to explicitly grant public access to VIT ID authentication routes
- Fixed 403 "Please sign in" error when unauthenticated users clicked "Log in with VIT ID" button
- The XML `is_public` flag alone was insufficient for CiviCRM Standalone; framework-level permission hook was required

### Technical Details

- Implemented `hook_civicrm_checkAccess()` to bypass CiviCRM's framework-level permission checks for public VIT ID routes
- This hook explicitly grants access to `/civicrm/vitid-auth0/login`, `/civicrm/vitid-auth0/callback`, and `/civicrm/vitid-auth0/error`
- Works in conjunction with the XML menu configuration from v1.1.8 to ensure full public access

## [1.1.8] - 2025-11-05

### Fixed

- **Critical bug fix**: Removed authentication requirement from public routes
- Fixed 403 "Please sign in" error when unauthenticated users clicked "Log in with VIT ID" button
- Login, callback, and error pages are now properly accessible to non-authenticated users

### Technical Details

- Removed `<access_arguments>access CiviCRM</access_arguments>` from public routes in `vitid_auth0.xml`
- Routes `/civicrm/vitid-auth0/login`, `/civicrm/vitid-auth0/callback`, and `/civicrm/vitid-auth0/error` are now fully public
- Only the settings page (`/civicrm/admin/vitid-auth0/settings`) retains authentication requirement
- This was a catch-22 bug: users couldn't access the login page to authenticate because it required authentication

## [1.1.7] - 2025-11-05

### Improved

- Implemented Post-Redirect-Get pattern for add/delete role mapping operations
- Page now automatically reloads after adding or deleting a role mapping
- Updated role mapping list immediately visible after operations
- Prevents accidental double-submission on page refresh

### Changed

- Add and delete operations now redirect back to settings page after completion
- Success messages persist through redirect via session

### Benefits

- Better user experience - immediate visual feedback of changes
- No confusion about whether operations succeeded
- No need to manually navigate away and back to see updated list
- Standard web UX pattern that prevents form resubmission issues

## [1.1.6] - 2025-11-05

### Fixed

- Fixed DAO error "No table definition for civicrm_vitid_role_mapping" when adding/deleting role mappings
- Fixed button placement - "Add Role Mapping" button now correctly positioned in Action column instead of at bottom of form

### Changed

- Replaced DAO `save()` and `delete()` operations with direct SQL queries using `CRM_Core_DAO::executeQuery()`
- Improved UI layout with better column widths (45%/45%/10%)

### Technical Details

- Uses parameterized SQL queries for security
- INSERT query: `INSERT INTO civicrm_vitid_role_mapping (auth0_role_name, civicrm_role_id, is_active) VALUES (%1, %2, 1)`
- DELETE query: `DELETE FROM civicrm_vitid_role_mapping WHERE id = %1`
- No DAO metadata required - direct SQL is simpler and more reliable for this use case

## [1.1.5] - 2025-11-05

### Changed

- Simplified role mapping management to use traditional form-based operations
- Role mappings now managed directly through form submission (add/delete)
- Removed all AJAX/API4 complexity for more reliable and maintainable code
- Page reloads after add/delete operations to show current database state

### Technical Details

- Attempted to use direct DAO operations in form's `postProcess()` method
- Traditional CiviCRM form pattern without external dependencies
- All logic contained in `CRM_VitidAuth0_Form_Settings` class
- Simple HTML table with form fields, no JavaScript required
- Each saved mapping displayed with Delete button
- Input row at top for adding new mappings
- Automatic CSRF protection from CiviCRM's form handling

### Known Issues

- DAO operations failed due to missing table metadata (fixed in v1.1.6)
- Button placement incorrect (fixed in v1.1.6)

## [1.1.4] - 2025-11-04

### Status: DEPRECATED - DO NOT USE

This version attempted to migrate to CiviCRM API4 but had entity discovery issues. The API4 entity `VitidAuth0RoleMapping` could not be discovered by CiviCRM, resulting in errors when accessing the settings page or API Explorer.

**Error:** `Unknown entity VitidAuth0RoleMapping`

This version was replaced by v1.1.5 which uses a simpler, more reliable form-based approach.

## [1.1.3] - 2025-11-04

### Changed

- Attempted migration to API4 for role mapping operations
- [Note: This version had incomplete API4 implementation - properly completed in v1.1.4]

## [1.1.2] - 2025-11-04

### Changed

- Experimented with API4 entity registration approaches
- [Note: This version had incomplete API4 implementation - properly completed in v1.1.4]

## [1.1.1] - 2025-11-04

### Added

- New API4 endpoints for role mapping management (`vitid_auth0_RoleMapping` entity)
- DAO class for role mapping database interactions

### Changed

- Restructured role mappings UI in settings form for improved clarity and usability
- Input row (for adding new mappings) now always remains at the top of the table
- Saved role mappings now displayed as read-only entries below the input row
- Removed bottom "Add Role Mapping" button for cleaner interface

### Improved

- Implemented AJAX-based add/delete operations for role mappings
- Users can now immediately see saved mappings in the UI
- Removed redundant form submission-based workflow for role mappings
- Input fields automatically clear after successful addition to prevent duplicate entries
- Delete operations now immediately remove entries from the database with confirmation
- Enhanced user feedback with status notifications for add/delete operations
- Prevents accidental duplicates by clearing input after successful save

### Fixed

- Fixed issue where saved role mappings were not visible in the UI after submission
- Improved form data structure for better handling of role mapping lifecycle

## [1.1.0] - 2025-04-11

### Added

- Implemented dedicated database table for role mappings (`civicrm_vitid_role_mapping`)
- Added automatic migration of existing role mappings from settings to new table on extension install
- New RoleMapper utility methods: `getAllMappings()`, `addMapping()`, `deleteMapping()`, `updateMappingStatus()`
- Improved role mapping storage with better structure for future enhancements

### Changed

- Migrated role mappings from CiviCRM settings (JSON) to dedicated database table
- Updated `RoleMapper::hasValidRoles()` to query the new table instead of settings
- Updated `RoleMapper::getCiviCRMRoles()` to query the new table instead of settings
- Updated Settings form to work with the new table-based role mappings
- Removed `vitid_auth0_role_mappings` setting definition

### Removed

- Removed role mappings storage in civicrm_setting table
- Removed old `vitid_auth0_role_mappings` setting key

### Technical Details

- New table `civicrm_vitid_role_mapping` with fields: id, auth0_role_name, civicrm_role_id, is_active, created_at, updated_at
- Table automatically created in `hook_civicrm_install()`
- Table automatically dropped in `hook_civicrm_uninstall()`
- Proper migration path for existing installations with automatic data transfer
- Auth0 credentials (domain, client_id, client_secret) remain in civicrm_setting table per CiviCRM best practices

## [1.0.16] - 2025-03-11

### Fixed

- Fixed uninstall hook to actually delete extension settings from database
- Changed from CiviCRM API v4 Setting::delete() to direct database query using CRM_Core_DAO
- Extension settings now reliably removed when uninstalled

### Technical Details

- Uninstall hook now uses `CRM_Core_DAO::executeQuery()` to directly delete from civicrm_setting table
- Direct SQL approach is more reliable than API v4 for this operation
- Deletes: vitid_auth0_domain, vitid_auth0_client_id, vitid_auth0_client_secret, vitid_auth0_role_mappings

## [1.0.15] - 2025-03-11

### Fixed

- Fixed uninstall hook to properly delete all extension settings from database
- Changed from using LIKE operator to explicit setting name deletion for reliability
- Extension settings now properly removed when uninstalled using CiviCRM API v4

### Technical Details

- Uninstall hook now deletes settings by exact name instead of pattern matching
- Deletes: vitid_auth0_domain, vitid_auth0_client_id, vitid_auth0_client_secret, vitid_auth0_role_mappings
- Uses `\Civi\Api4\Setting::delete()` with explicit where clause for each setting name

## [1.0.14] - 2025-03-11

### Fixed

- Implemented proper cleanup on uninstall using CiviCRM API v4
- Extension settings are now automatically deleted from the database when the extension is uninstalled
- Prevents orphaned data in `civicrm_setting` table after extension removal

### Technical Details

- Added `hook_civicrm_uninstall()` implementation using `\Civi\Api4\Setting::delete()`
- Deletes all settings matching pattern `vitid_auth0_%`
- Includes proper error handling and logging for cleanup operations

## [1.0.13] - 2025-03-11

### Changed

- Migrated all API calls from API v3 to API v4 for better compatibility and future-proofing
- Updated `getCiviCRMRolesList()` to use `\Civi\Api4\Role::get()`
- Updated `createUserSession()` to use API v4 for Contact and UFMatch entities
- Updated `validateUser()` to use API v4 for Contact entity

### Technical Details

- API v4 is the current actively maintained API in CiviCRM
- Uses modern object-oriented syntax with fluent interface
- Better performance and more consistent behavior across different CiviCRM installations
- Essential for proper functionality with CiviCRM Standalone 6.1+

## [1.0.12] - 2025-03-11

### Fixed

- Fixed empty CiviCRM role dropdown in settings page for CiviCRM Standalone installations
- Updated `getCiviCRMRolesList()` method to use the `Role` API entity instead of `OptionValue` API
- Resolved compatibility issue where the extension was looking for the non-existent `user_role` option group in Standalone
- The role dropdown now correctly displays all active CiviCRM roles (e.g., Administrator, everyone)

### Technical Details

- Changed from `civicrm_api3('OptionValue', 'get', ['option_group_id' => 'user_role'])` to `civicrm_api3('Role', 'get')`
- Updated array key from `$role['value']` to `$role['id']` to match Role API structure
- Tested with CiviCRM Standalone 6.1.2 and PHP 8.3

## [1.0.11] - 2025-03-11

### Initial Release

- Previous stable release
