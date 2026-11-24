/**
 * VIT ID Authentication Login Button Injection for Angular Login Page
 * 
 * This script handles injection of the VIT ID login button on CiviCRM 6.7.2+
 * Angular-based login pages. It waits for Angular to render the login form,
 * then injects the button and handles form visibility toggling.
 */

(function($) {
  'use strict';

  // The stylesheet hides the standard login fields only while this class is set.
  // Set it now to avoid a flash of the form; remove it if the button cannot be
  // added, so the standard form stays usable.
  document.documentElement.classList.add('vitid-auth0-active');

  function giveUp(reason) {
    console.error('VIT ID Auth0: ' + reason + '; showing the standard login form');
    document.documentElement.classList.remove('vitid-auth0-active');
  }

  /**
   * Wait for Angular module to be ready
   */
  function waitForAngular(callback, maxAttempts) {
    maxAttempts = maxAttempts || 50;
    var attempts = 0;
    
    function checkAngular() {
      attempts++;
      
      // Check if Angular is loaded and crmLogin module is available.
      // angular.module(name) throws while the module is not registered yet,
      // which must not end the retries.
      var ready = false;
      try {
        ready = typeof angular !== 'undefined' && !!angular.module && !!angular.module('crmLogin');
      }
      catch (e) {
        ready = false;
      }
      if (ready) {
        callback();
        return;
      }
      
      if (attempts < maxAttempts) {
        setTimeout(checkAngular, 100);
      }
      else {
        giveUp('login module not found');
      }
    }
    
    checkAngular();
  }

  /**
   * Wait for login form to be rendered by Angular
   */
  function waitForLoginForm(callback, maxAttempts) {
    maxAttempts = maxAttempts || 100;
    var attempts = 0;
    
    function checkForm() {
      attempts++;
      
      // Look for form with username and password inputs
      var form = document.querySelector('form input[name="name"]');
      if (form) {
        form = form.closest('form');
        if (form) {
          callback(form);
          return;
        }
      }
      
      // Alternative: look for form with password input
      if (!form) {
        form = document.querySelector('form input[type="password"]');
        if (form) {
          form = form.closest('form');
          if (form) {
            callback(form);
            return;
          }
        }
      }
      
      if (attempts < maxAttempts) {
        setTimeout(checkForm, 100);
      }
      else {
        giveUp('login form not found');
      }
    }
    
    checkForm();
  }

  /**
   * Inject VIT ID login button
   */
  function injectVitIdButton(form) {
    // Check if button already exists
    if (document.querySelector('.vitid-auth0-login')) {
      return;
    }
    
    // Get login URL from CRM.vars
    var loginUrl = '';
    if (typeof CRM !== 'undefined' && CRM.vars && CRM.vars.vitidAuth0) {
      loginUrl = CRM.vars.vitidAuth0.loginUrl;
    }
    
    if (!loginUrl) {
      giveUp('login URL not found in CRM.vars');
      return;
    }
    
    // Create button HTML - use DOM methods to avoid XSS issues
    var buttonContainer = document.createElement('div');
    buttonContainer.className = 'vitid-auth0-login';
    
    var buttonLink = document.createElement('a');
    buttonLink.href = loginUrl;
    buttonLink.className = 'vitid-auth0-button';
    
    var icon = document.createElement('i');
    icon.className = 'crm-i fa-sign-in';
    
    buttonLink.appendChild(icon);
    buttonLink.appendChild(document.createTextNode(' Log in with VIT ID'));
    buttonContainer.appendChild(buttonLink);
    
    // Find the best place to inject the button
    // Try to find the standalone-auth-box (which contains the Angular component)
    var container = document.querySelector('.standalone-auth-box');
    
    if (container) {
      // Find the crm-angular-js element to insert before it
      var angularComponent = container.querySelector('crm-angular-js');
      if (angularComponent) {
        // Insert before the Angular component
        container.insertBefore(buttonContainer, angularComponent);
      } else {
        // Fallback: insert at the beginning of the box (after logo)
        var logo = container.querySelector('.crm-logo');
        if (logo && logo.nextSibling) {
          container.insertBefore(buttonContainer, logo.nextSibling);
        } else {
          container.insertBefore(buttonContainer, container.firstChild);
        }
      }
    } else {
      // Fallback: try standalone-auth-form
      container = document.querySelector('.standalone-auth-form');
      if (container) {
        // Insert at the beginning of the form container
        container.insertBefore(buttonContainer, container.firstChild);
      } else {
        // Last resort: insert right before the form's parent
        if (form && form.parentNode) {
          form.parentNode.insertBefore(buttonContainer, form);
        }
      }
    }
    
    // Add toggle button functionality
    addToggleButton(form);
    
    // Hide form fields by default
    hideFormFields(form);
    
    // Check localStorage for saved preference
    checkFormVisibilityPreference(form);
  }

  /**
   * Hide form fields by default
   */
  function hideFormFields(form) {
    if (!form) {
      return;
    }
    
    // Hide input wrappers
    var inputWrappers = form.querySelectorAll('.input-wrapper');
    inputWrappers.forEach(function(wrapper) {
      wrapper.style.display = 'none';
      wrapper.style.visibility = 'hidden';
    });
    
    // Hide login-or-forgot div
    var loginOrForgot = form.querySelector('.login-or-forgot');
    if (loginOrForgot) {
      loginOrForgot.style.display = 'none';
      loginOrForgot.style.visibility = 'hidden';
    }
    
    // Hide username and password inputs directly
    var usernameInput = form.querySelector('input[name="name"]');
    var passwordInput = form.querySelector('input[name="pass"], input[type="password"]');
    
    if (usernameInput) {
      var usernameWrapper = usernameInput.closest('.input-wrapper') || 
                           usernameInput.closest('div') || 
                           usernameInput.parentElement;
      if (usernameWrapper) {
        usernameWrapper.style.display = 'none';
        usernameWrapper.style.visibility = 'hidden';
      }
    }
    
    if (passwordInput) {
      var passwordWrapper = passwordInput.closest('.input-wrapper') || 
                           passwordInput.closest('div') || 
                           passwordInput.parentElement;
      if (passwordWrapper) {
        passwordWrapper.style.display = 'none';
        passwordWrapper.style.visibility = 'hidden';
      }
    }
    
    // Hide submit button
    var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitButton) {
      var buttonWrapper = submitButton.closest('.login-or-forgot') ||
                         submitButton.closest('div') ||
                         submitButton.parentElement;
      if (buttonWrapper) {
        buttonWrapper.style.display = 'none';
        buttonWrapper.style.visibility = 'hidden';
      }
    }
    
    // Hide "Forgotten password" link
    var forgotLink = form.querySelector('a[href*="forgot"], a[href*="password"]');
    if (forgotLink) {
      forgotLink.style.display = 'none';
      forgotLink.style.visibility = 'hidden';
    }
  }

  /**
   * Show form fields
   */
  function showFormFields(form) {
    if (!form) {
      return;
    }
    
    // Show input wrappers
    var inputWrappers = form.querySelectorAll('.input-wrapper');
    inputWrappers.forEach(function(wrapper) {
      wrapper.style.display = '';
      wrapper.style.visibility = '';
    });
    
    // Show login-or-forgot div
    var loginOrForgot = form.querySelector('.login-or-forgot');
    if (loginOrForgot) {
      loginOrForgot.style.display = '';
      loginOrForgot.style.visibility = '';
    }
    
    // Show username and password inputs
    var usernameInput = form.querySelector('input[name="name"]');
    var passwordInput = form.querySelector('input[name="pass"], input[type="password"]');
    
    if (usernameInput) {
      var usernameWrapper = usernameInput.closest('.input-wrapper') || 
                           usernameInput.closest('div') || 
                           usernameInput.parentElement;
      if (usernameWrapper) {
        usernameWrapper.style.display = '';
        usernameWrapper.style.visibility = '';
      }
    }
    
    if (passwordInput) {
      var passwordWrapper = passwordInput.closest('.input-wrapper') || 
                           passwordInput.closest('div') || 
                           passwordInput.parentElement;
      if (passwordWrapper) {
        passwordWrapper.style.display = '';
        passwordWrapper.style.visibility = '';
      }
    }
    
    // Show submit button
    var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitButton) {
      var buttonWrapper = submitButton.closest('.login-or-forgot') ||
                         submitButton.closest('div') ||
                         submitButton.parentElement;
      if (buttonWrapper) {
        buttonWrapper.style.display = '';
        buttonWrapper.style.visibility = '';
      }
    }
    
    // Show "Forgotten password" link
    var forgotLink = form.querySelector('a[href*="forgot"], a[href*="password"]');
    if (forgotLink) {
      forgotLink.style.display = '';
      forgotLink.style.visibility = '';
    }
  }

  /**
   * Add toggle button to show/hide standard login form
   */
  function addToggleButton(form) {
    if (!form) {
      return;
    }
    
    // Check if toggle button already exists
    if (document.querySelector('.vitid-auth0-toggle-container')) {
      return;
    }
    
    // Create toggle button container
    var toggleContainer = document.createElement('div');
    toggleContainer.className = 'vitid-auth0-toggle-container';
    
    var toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'vitid-auth0-toggle-btn';
    toggleButton.innerHTML = '<span class="vitid-auth0-toggle-text">Show standard login form</span>';
    
    toggleButton.addEventListener('click', function() {
      if (form.classList.contains('show-standard-form')) {
        // Hide form
        form.classList.remove('show-standard-form');
        hideFormFields(form);
        toggleButton.querySelector('.vitid-auth0-toggle-text').textContent = 'Show standard login form';
        localStorage.setItem('vitid_auth0_show_form', 'false');
      } else {
        // Show form
        form.classList.add('show-standard-form');
        showFormFields(form);
        toggleButton.querySelector('.vitid-auth0-toggle-text').textContent = 'Hide standard login form';
        localStorage.setItem('vitid_auth0_show_form', 'true');
      }
    });
    
    toggleContainer.appendChild(toggleButton);
    
    // Insert after the form
    if (form.parentNode) {
      form.parentNode.insertBefore(toggleContainer, form.nextSibling);
    }
  }

  /**
   * Check localStorage for saved form visibility preference
   */
  function checkFormVisibilityPreference(form) {
    if (!form) {
      return;
    }
    
    var showForm = localStorage.getItem('vitid_auth0_show_form');
    if (showForm === 'true') {
      form.classList.add('show-standard-form');
      showFormFields(form);
      
      // Update toggle button text if it exists
      var toggleButton = document.querySelector('.vitid-auth0-toggle-btn');
      if (toggleButton) {
        toggleButton.querySelector('.vitid-auth0-toggle-text').textContent = 'Hide standard login form';
      }
    }
  }

  /**
   * Initialize when DOM is ready
   */
  function init() {
    // Wait for Angular to be ready
    waitForAngular(function() {
      // Wait for login form to be rendered
      waitForLoginForm(function(form) {
        try {
          injectVitIdButton(form);
        }
        catch (e) {
          giveUp('VIT ID button could not be added (' + e.message + ')');
        }
      });
    });
  }

  // Initialize when document is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(CRM.$ || jQuery);

