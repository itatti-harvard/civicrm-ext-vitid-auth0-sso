# Installation Guide for VIT ID Authentication Extension

This guide provides step-by-step instructions for installing and configuring the VIT ID Authentication extension.

## Pre-Installation Checklist

Before you begin, ensure you have:

- [ ] CiviCRM Standalone 6.1.2+ running in Docker
- [ ] SSH/terminal access to your server
- [ ] CiviCRM admin credentials
- [ ] Auth0 account access
- [ ] Composer installed on your server

## Step 1: Deploy Extension Package

### Option A: Deploy Release Package (Recommended)

Transfer the release package to your server and extract it:

```bash
# 1. Copy the release package to your server
scp vitid_auth0-v1.0.0.zip user@your-server:/home/vitadmin/civicrm/ext/

# 2. SSH into your server
ssh user@your-server

# 3. Navigate to extensions directory
cd /home/vitadmin/civicrm/ext/

# 4. Extract the package
unzip vitid_auth0-v1.0.0.zip

# 5. Verify extraction
ls -la vitid_auth0-v1.0.0/
```

The release package includes all dependencies - no additional setup required!

### Option B: Deploy via Docker Volume (If using Docker)

If your CiviCRM runs in Docker with mounted volumes:

```bash
# 1. Copy the release package to the mounted volume
cp vitid_auth0-v1.0.0.zip /home/vitadmin/civicrm/ext/

# 2. Extract inside the container or on the host
cd /home/vitadmin/civicrm/ext/
unzip vitid_auth0-v1.0.0.zip
```

## Step 2: Set Correct Permissions

Ensure the web server can read the extension files:

```bash
# Adjust ownership to match your web server user (usually www-data)
sudo chown -R www-data:www-data /home/vitadmin/civicrm/ext/vitid_auth0-v1.0.0

# Set appropriate permissions
sudo chmod -R 755 /home/vitadmin/civicrm/ext/vitid_auth0-v1.0.0
```

## Step 3: Enable Extension in CiviCRM

1. Open your browser and log in to CiviCRM as administrator:

   ```
   https://example.org/civicrm/login
   ```

2. Navigate to **Administer** → **System Settings** → **Extensions**

3. You should see "VIT ID Authentication" in the list of extensions

4. Click the **Install** button next to it

5. Wait for the installation to complete (you should see a success message)

6. Verify the extension is now listed as "Enabled"

## Step 4: Configure Auth0 Application

### Create Auth0 Application

1. Log in to [Auth0 Dashboard](https://manage.auth0.com)

2. Go to **Applications** → **Applications**

3. Click **Create Application**

4. Configure:
   - **Name**: CiviCRM VIT ID
   - **Application Type**: Regular Web Application
   - Click **Create**

### Configure Application Settings

1. Go to the **Settings** tab

2. Note these values (you'll need them later):

   - **Domain**: e.g., `your-tenant.auth0.com`
   - **Client ID**: e.g., `abc123...`
   - **Client Secret**: e.g., `xyz789...` (click "Show" to reveal)

3. Scroll down to **Application URIs** and configure:

   - **Allowed Callback URLs**:
     ```
     https://example.org/civicrm/vitid-auth0/callback
     ```
   - **Allowed Logout URLs**:
     ```
     https://example.org
     ```
   - **Allowed Web Origins**:
     ```
     https://example.org
     ```

4. Scroll to **Advanced Settings** → **Grant Types**

   - Ensure these are checked:
     - ✅ Authorization Code
     - ✅ Refresh Token

5. Click **Save Changes** at the bottom

### Enable Management API Access

1. Go to **Applications** → **APIs** → **Auth0 Management API**

2. Go to **Machine to Machine Applications** tab

3. Find your "CiviCRM VIT ID" application

4. Toggle it **ON** (authorize it)

5. Click the expand arrow to see scopes

6. Select these scopes:

   - ✅ `read:roles`
   - ✅ `read:users`

7. Click **Update**

## Step 5: Configure Extension in CiviCRM

1. In CiviCRM, navigate to:
   **Administer** → **System Settings** → **VIT ID Authentication Settings**

2. Fill in the Auth0 credentials:

   - **Auth0 Domain**: `your-tenant.auth0.com` (without https://)
   - **Auth0 Client ID**: Paste from Auth0 dashboard
   - **Auth0 Client Secret**: Paste from Auth0 dashboard

3. Note the **Callback URL** displayed on the page

4. Click **Save Settings**

5. If configured correctly, the page should reload and display Auth0 roles

## Step 6: Configure Role Mappings

1. On the same settings page, scroll down to **Role Mappings**

2. For each Auth0 role you want to allow:

   - Select the corresponding CiviCRM role(s) from the dropdown
   - You can select multiple CiviCRM roles per Auth0 role
   - Hold Ctrl (Windows/Linux) or Cmd (Mac) to select multiple

3. Click **Save Settings**

**Important**: Only users with at least one mapped role can access CiviCRM

## Step 7: Configure Auth0 Users

For each user who should have access:

### Add CiviCRM Contact ID

1. In Auth0 Dashboard, go to **User Management** → **Users**

2. Select the user

3. Scroll to **app_metadata** section

4. Click **Edit**

5. Add this JSON (replace `3926` with the user's CiviCRM contact ID):

   ```json
   {
     "civicrm_id": "3926"
   }
   ```

6. Click **Save**

### Assign Roles

1. While viewing the user, scroll to **Roles** section

2. Click **Assign Roles**

3. Select the appropriate role(s)

4. Click **Assign**

## Step 8: Configure Auth0 to Include Roles in Token

### Create Custom Action

1. In Auth0 Dashboard, go to **Actions** → **Library**

2. Click **Build Custom**

3. Configure:

   - **Name**: Add CiviCRM Claims
   - **Trigger**: Login / Post Login
   - **Runtime**: Node 18 (Recommended)

4. Replace the code with:

```javascript
exports.onExecutePostLogin = async (event, api) => {
  const namespace = "https://civicrm.org";

  // Add roles to ID token
  if (event.authorization && event.authorization.roles) {
    api.idToken.setCustomClaim(`${namespace}/roles`, event.authorization.roles);
  }

  // Add app_metadata to ID token
  if (event.user.app_metadata) {
    api.idToken.setCustomClaim(
      `${namespace}/app_metadata`,
      event.user.app_metadata
    );
  }
};
```

5. Click **Deploy**

### Add Action to Login Flow

1. Go to **Actions** → **Flows** → **Login**

2. Drag your "Add CiviCRM Claims" action from the right panel to the flow

3. Place it between "Start" and "Complete"

4. Click **Apply**

## Step 9: Test the Integration

### Test VIT ID Login

1. Open a new incognito/private browser window

2. Go to: `https://example.org/civicrm/login`

3. You should see the "Log in with VIT ID" button

4. Click it - you should be redirected to Auth0

5. Enter your Auth0 credentials

6. After authentication, you should be redirected back to CiviCRM dashboard

### Test Local Admin Login

1. In another browser or tab, try logging in with local admin credentials

2. Local admin login should still work normally

### Test Logout

1. While logged in via VIT ID, click logout

2. You should be logged out of both CiviCRM and Auth0

3. If you click "Log in with VIT ID" again immediately, you may be automatically logged back in (if Auth0 session is still active)

## Advanced: Building from Source

If you need to build the extension from source code:

### Prerequisites

- Composer installed on your local machine
- Git (if cloning from repository)

### Build Process

```bash
# 1. Get the source code
git clone <repository-url>
cd vitid_auth0

# 2. Run the build script
./build-release.sh

# 3. This creates vitid_auth0-v1.0.0.zip ready for deployment
```

The build script will:

- Install all dependencies via composer
- Create a clean release package
- Exclude development files
- Include the vendor directory

### Manual Build (Alternative)

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Create package
zip -r vitid_auth0-v1.0.0.zip vitid_auth0 \
  -x "*.git*" -x "*.DS_Store" -x "*.zip"
```

## Troubleshooting Installation

### Extension Won't Install

**Error**: "Unable to install extension"

**Solution**:

- Check file permissions: `ls -la /home/vitadmin/civicrm/ext/vitid_auth0-v1.0.0`
- Ensure vendor directory exists: `ls /home/vitadmin/civicrm/ext/vitid_auth0-v1.0.0/vendor`
- Check CiviCRM error log: `tail -f /var/www/html/private/ConfigAndLog/CiviCRM.*.log`

### Package Extraction Issues

**Error**: "Cannot create directory"

**Solution**:

```bash
# Ensure you have write permissions
ls -la /home/vitadmin/civicrm/ext/

# If needed, adjust permissions
sudo chown -R $USER:$USER /home/vitadmin/civicrm/ext/
```

### Login Button Not Appearing

**Solutions**:

1. Clear CiviCRM cache:

   - Navigate to: **Administer** → **System Settings** → **Cleanup Caches**
   - Click "Clear all caches"

2. Verify extension is enabled:

   - Check **Administer** → **System Settings** → **Extensions**

3. Check browser console for errors (F12)

### Role Mappings Not Loading

**Error**: "Unable to fetch Auth0 roles"

**Solutions**:

1. Verify Auth0 credentials are correct
2. Check Management API is authorized (Step 5)
3. Test Auth0 connectivity from server:
   ```bash
   curl https://your-tenant.auth0.com/
   ```

### 403 "Access Denied" on the VIT ID Login Link After an Upgrade

After replacing the extension directory (for example `vitid_auth0-v1.9.4` with `vitid_auth0-v1.9.5`), CiviCRM may keep the old route table and the VIT ID login link answers 403. Rebuild the menu:

```bash
docker exec <civicrm-container> cv flush
docker exec <civicrm-container> cv ev 'CRM_Core_Menu::store();'
```

Or, logged in as an administrator, open `https://your-site/civicrm/menu/rebuild?reset=1`.

### "Class not found" Errors

**Error**: "Class 'Auth0\SDK\Auth0' not found"

**Solution**:

This usually means the release package wasn't extracted properly or the vendor directory is missing.

```bash
# Check if vendor directory exists
ls -la /home/vitadmin/civicrm/ext/vitid_auth0-v1.0.0/vendor/

# If missing, you may have extracted incorrectly
# Re-extract the release package or build from source (see Advanced section)
```

## Verification Checklist

After installation, verify:

- [ ] Extension shows as "Enabled" in Extensions page
- [ ] "Log in with VIT ID" button appears on login page
- [ ] Settings page accessible at: Administer → System Settings → VIT ID Authentication Settings
- [ ] Auth0 roles load in settings page
- [ ] Test user can log in via VIT ID
- [ ] Local admin can still log in
- [ ] Logout works for both VIT ID and local users
- [ ] User without mapped role sees error message
- [ ] User without civicrm_id sees appropriate error

## Next Steps

- Add civicrm_id to all Auth0 users' app_metadata
- Assign roles to users in Auth0
- Configure role mappings for your organization
- Test with multiple users
- Document your organization-specific role mapping scheme

## Support

For issues during installation:

- Check CiviCRM logs: `/var/www/html/private/ConfigAndLog/`
- Check Auth0 logs: Auth0 Dashboard → Monitoring → Logs
- Review PHP error logs: `tail -f /var/log/php8.1-fpm.log`
