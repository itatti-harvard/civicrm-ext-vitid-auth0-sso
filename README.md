# VIT ID Authentication Extension for CiviCRM Standalone

This extension provides Auth0-based single sign-on (VIT ID) authentication for CiviCRM Standalone instances.

## Features

- **Single Sign-On**: Users can log in with their VIT ID (Auth0) credentials
- **Login Button**: Adds a "Log in with VIT ID" button to the CiviCRM Standalone login page
- **Role Mapping**: Map Auth0 roles to CiviCRM roles via admin interface
- **Role-Based Access**: Only users with mapped roles can access CiviCRM
- **Single Logout**: Automatically logs users out of both CiviCRM and Auth0
- **Mixed Authentication**: Supports both VIT ID and local admin accounts

## Requirements

- CiviCRM Standalone 6.1.2 or higher
- PHP 8.2 or higher
- Composer (for dependency installation)
- Auth0 account with configured application

## Installation

### Quick Install (Recommended)

Download the latest release package and deploy:

```bash
# 1. Download the release package (replace URL with actual download)
# Or if you have the zip file locally, copy it to your server:
scp vitid_auth0-v1.0.0.zip user@your-server:/home/vitadmin/civicrm/ext/

# 2. On your server, unzip in the extensions directory
cd /home/vitadmin/civicrm/ext/
unzip vitid_auth0-v1.0.0.zip

# 3. Set proper permissions
sudo chown -R www-data:www-data vitid_auth0-v1.0.0
sudo chmod -R 755 vitid_auth0-v1.0.0
```

The release package includes all dependencies - no additional setup required!

### Enable Extension in CiviCRM

1. Log in to CiviCRM as an administrator
2. Navigate to **Administer** > **System Settings** > **Extensions**
3. Find "VIT ID Authentication" in the list
4. Click **Install** (or **Enable** if already installed)

### Building from Source (For Developers)

If you need to build from source code:

```bash
# Clone or download the source code
cd vitid_auth0

# Run the build script (requires composer on your local machine)
./build-release.sh

# This creates vitid_auth0-v1.0.0.zip ready for deployment
```

See [RELEASE.md](RELEASE.md) for detailed build instructions.

## Configuration

### Step 1: Configure Auth0 Application

1. Log in to your Auth0 dashboard
2. Create a new **Regular Web Application** (or use existing)
3. Configure the application:

   - **Application Type**: Regular Web Application
   - **Token Endpoint Authentication Method**: POST
   - **Allowed Callback URLs**: `https://civicrmurl/civicrm/vitid-auth0/callback`
   - **Allowed Logout URLs**: `https://civicrmurl`
   - **Allowed Web Origins**: `https://civicrmurl`

4. Note your application credentials:
   - Domain (e.g., `your-tenant.auth0.com`)
   - Client ID
   - Client Secret

### Step 2: Configure Extension in CiviCRM

1. Navigate to **Administer** > **System Settings** > **VIT ID Authentication Settings**
2. Enter your Auth0 credentials:
   - **Auth0 Domain**: Your tenant domain (without https://)
   - **Auth0 Client ID**: From Auth0 application settings
   - **Auth0 Client Secret**: From Auth0 application settings
3. Click **Save Settings**

### Step 3: Configure Role Mappings

After saving your Auth0 credentials:

1. The settings page will display all available Auth0 roles
2. For each Auth0 role, select which CiviCRM role(s) it should map to
3. Users must have at least one mapped role to access CiviCRM
4. Click **Save Settings**

### Step 4: Configure Auth0 User Metadata

For each user in Auth0, add their CiviCRM contact ID to app_metadata:

```json
{
  "civicrm_id": "3926"
}
```

To add this in Auth0:

1. Go to **User Management** > **Users**
2. Select a user
3. Scroll to **app_metadata** section
4. Add the JSON above with the user's CiviCRM contact ID
5. Save

### Step 5: Configure Auth0 Roles in ID Token

Ensure Auth0 includes roles in the ID token:

1. In Auth0 dashboard, go to **Actions** > **Flows** > **Login**
2. Create a custom action or use the built-in role assignment
3. Add roles to the ID token with this code:

```javascript
exports.onExecutePostLogin = async (event, api) => {
  const namespace = "https://civicrm.org";
  if (event.authorization) {
    api.idToken.setCustomClaim(`${namespace}/roles`, event.authorization.roles);
    api.idToken.setCustomClaim(
      `${namespace}/app_metadata`,
      event.user.app_metadata
    );
  }
};
```

## Usage

### For Users

1. Visit your CiviCRM login page: `https://example.org/civicrm/login`
2. Click the **"Log in with VIT ID"** button
3. You'll be redirected to Auth0 to authenticate
4. After successful authentication, you'll be logged into CiviCRM
5. To log out, use the standard CiviCRM logout (logs out of both systems)

### For Administrators

- **Local Admin Access**: Local admin accounts work normally alongside VIT ID
- **Settings**: Access via **Administer** > **System Settings** > **VIT ID Authentication Settings**
- **Role Management**: Update role mappings anytime via the settings page
- **User Management**: Manage users and their app_metadata in Auth0

## Troubleshooting

### Login Button Not Appearing

- Check that the extension is enabled
- Clear CiviCRM cache: **Administer** > **System Settings** > **Cleanup Caches and Update Paths**

### "VIT ID authentication is not configured" Error

- Verify Auth0 credentials in extension settings
- Ensure all three fields (Domain, Client ID, Client Secret) are filled

### "Your VIT ID account is not linked to a CiviCRM contact"

- Add the user's `civicrm_id` to their Auth0 app_metadata
- Ensure the contact ID exists in CiviCRM

### "You don't have the required permissions"

- Check that the user has at least one Auth0 role
- Verify that role is mapped to a CiviCRM role in settings
- Ensure Auth0 is sending roles in the ID token

### Role Mappings Not Loading

- Verify Auth0 credentials are correct
- Check that Auth0 Management API is accessible
- Review CiviCRM error logs: `civicrm/ConfigAndLog/CiviCRM.*.log`

### HTTP 500 on the VIT ID Login Link After Upgrading CiviCRM

If the container log shows `Declaration of Psr\Log\AbstractLogger::emergency() must be compatible`, the extension is older than 1.9.5 and bundles its own `psr/log`, which clashes with the copy CiviCRM 6.15+ ships. Upgrade the extension to 1.9.5 or later.

### HTTP 403 on the VIT ID Login Link After Replacing the Extension Directory

CiviCRM kept the old route table. Rebuild the menu with `cv flush` followed by `cv ev 'CRM_Core_Menu::store();'`, or open `civicrm/menu/rebuild?reset=1` as an administrator.

### Dependencies Not Loading

If you see class not found errors:

```bash
cd /home/vitadmin/civicrm/ext/vitid_auth0
composer install --no-dev
```

## Security Considerations

- **Client Secret**: Keep your Auth0 client secret secure
- **HTTPS**: Always use HTTPS in production
- **State Parameter**: CSRF protection is built-in via state parameter
- **Session Security**: Uses CiviCRM's native session handling
- **Role Validation**: Users are validated on every login

## File Structure

```
vitid_auth0/
├── CRM/VitidAuth0/
│   ├── Form/
│   │   └── Settings.php          # Admin settings form
│   ├── Page/
│   │   ├── Callback.php          # OAuth callback handler
│   │   ├── Error.php             # Error page
│   │   └── Login.php             # Login initiator
│   └── Utils/
│       ├── Auth0Client.php       # Auth0 SDK wrapper
│       └── RoleMapper.php        # Role mapping logic
├── templates/
│   └── CRM/VitidAuth0/
│       ├── Form/
│       │   └── Settings.tpl      # Settings form template
│       └── Page/
│           └── Error.tpl         # Error page template
├── xml/
│   └── Menu/
│       └── vitid_auth0.xml       # URL routing
├── settings/
│   └── vitid_auth0.setting.php   # Settings definitions
├── composer.json                  # PHP dependencies
├── info.xml                       # Extension metadata
├── vitid_auth0.php               # Hook implementations
└── vitid_auth0.civix.php         # Civix helper functions
```

## Support

For issues or questions:

- Check CiviCRM logs: `civicrm/ConfigAndLog/`
- Review Auth0 logs in Auth0 dashboard
- Contact your system administrator

## License

AGPL-3.0

## Author

Andrea Caselli
