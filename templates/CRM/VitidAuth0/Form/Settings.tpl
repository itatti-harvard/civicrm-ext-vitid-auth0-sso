<div class="crm-container">
  <h3>{ts}VIT ID Authentication Settings{/ts}</h3>
  
  <div class="crm-block crm-form-block">
    <div class="help">
      <p>{ts}Configure VIT ID (Auth0) single sign-on authentication for CiviCRM Standalone.{/ts}</p>
    </div>

    <div class="crm-section">
      <div class="label">{$form.vitid_auth0_domain.label}</div>
      <div class="content">
        {$form.vitid_auth0_domain.html}
        <div class="description">{ts}Your Auth0 tenant domain or custom domain, without https://{/ts}</div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.vitid_auth0_client_id.label}</div>
      <div class="content">
        {$form.vitid_auth0_client_id.html}
        <div class="description">{ts}Found in your Auth0 application settings{/ts}</div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.vitid_auth0_client_secret.label}</div>
      <div class="content">
        {$form.vitid_auth0_client_secret.html}
        <div class="description">{if $hasClientSecret}{ts}A secret is saved. Leave this empty to keep it, or enter a new one to replace it.{/ts}{else}{ts}Found in your Auth0 application settings.{/ts}{/if}</div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.vitid_auth0_debug.label}</div>
      <div class="content">
        {$form.vitid_auth0_debug.html}
        <div class="description">{ts}Enable detailed debug logging for troubleshooting. Disable in production for performance.{/ts}</div>
      </div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{ts}Callback URL{/ts}</div>
      <div class="content">
        <input type="text" value="{$callbackUrl}" readonly="readonly" size="60" style="background-color: #f5f5f5;" />
        <div class="description">{ts}Add this URL to your Auth0 application's "Allowed Callback URLs" setting{/ts}</div>
      </div>
      <div class="clear"></div>
    </div>

    <h3>{ts}Role Mappings{/ts}</h3>
    <div class="help">
      <p>{ts}Map Auth0 role names to CiviCRM roles. Users must have at least one mapped role to access CiviCRM.{/ts}</p>
      <p><strong>{ts}Note:{/ts}</strong> {ts}Enter the Auth0 role name exactly as it appears in your Auth0 configuration (e.g., "civicrm-administrator").{/ts}</p>
    </div>

    <div class="crm-section">
      <table class="form-layout" style="width: 100%; margin-bottom: 20px;">
        <thead>
          <tr style="background-color: #f5f5f5;">
            <th style="width: 45%; padding: 8px;">{ts}Auth0 Role Name{/ts}</th>
            <th style="width: 45%; padding: 8px;">{ts}CiviCRM Role{/ts}</th>
            <th style="width: 10%; padding: 8px; text-align: center;">{ts}Action{/ts}</th>
          </tr>
        </thead>
        <tbody>
          <tr style="background-color: #f9f9f9;">
            <td style="padding: 8px;">{$form.auth0_role_name.html}</td>
            <td style="padding: 8px;">{$form.civicrm_role_id.html}</td>
            <td style="padding: 8px; text-align: center;">
              <button type="submit" name="_qf_Settings_submit_add_mapping" class="button" title="{ts}Add{/ts}">
                <i class="crm-i fa-plus"></i> {ts}Add{/ts}
              </button>
            </td>
          </tr>
          {if $currentMappings && count($currentMappings) > 0}
            {foreach from=$currentMappings item=mapping}
              <tr>
                <td style="padding: 8px;">{$mapping.auth0_role_name}</td>
                <td style="padding: 8px;">{$civiRoles[$mapping.civicrm_role_id]}</td>
                <td style="padding: 8px; text-align: center;">
                  <button type="submit" name="_qf_Settings_submit_delete_{$mapping.id}" class="button" title="{ts}Delete{/ts}" onclick="return confirm('{ts escape="js"}Are you sure you want to delete this role mapping?{/ts}');">
                    <i class="crm-i fa-trash"></i> {ts}Delete{/ts}
                  </button>
                </td>
              </tr>
            {/foreach}
          {else}
            <tr>
              <td colspan="3" style="padding: 8px; text-align: center; color: #666; font-style: italic;">
                {ts}No role mappings defined yet. Add one above.{/ts}
              </td>
            </tr>
          {/if}
        </tbody>
      </table>
    </div>

    <div class="crm-submit-buttons">
      <button type="submit" name="_qf_Settings_submit" class="button validate crm-form-submit default">
        <i class="crm-i fa-check"></i> {ts}Save Settings{/ts}
      </button>
      <button type="submit" name="_qf_Settings_cancel" class="button cancel crm-form-submit">
        <i class="crm-i fa-times"></i> {ts}Cancel{/ts}
      </button>
    </div>
  </div>
</div>
