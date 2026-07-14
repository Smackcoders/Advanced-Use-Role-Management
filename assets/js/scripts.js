jQuery(document).ready(function ($) {

  // Tab switching functionality
  const userTab = $("#tab-user-role-management");
  const assignTab = $("#tab-assign-temp-role");
  const userContent = $("#user-role-management-content");
  const assignContent = $("#assign-temp-role-content");

  if (userTab.length && assignTab.length && userContent.length && assignContent.length) {
    userTab.on("click", function () {
      userContent.show();
      assignContent.hide();
      userTab.addClass("nav-tab-active").attr("aria-selected", "true");
      assignTab.removeClass("nav-tab-active").attr("aria-selected", "false");
    });

    assignTab.on("click", function () {
      userContent.hide();
      assignContent.show();
      assignTab.addClass("nav-tab-active").attr("aria-selected", "true");
      userTab.removeClass("nav-tab-active").attr("aria-selected", "false");
    });
  }

  // Role selection dropdowns in Bulk Actions
  $("#add_role_dropdown").on("change", function () {
    if ($(this).val() === "administrator") {
      if (!confirm("Warning: You are selecting the Administrator role. Are you sure you want to proceed?")) {
        $(this).val("");
      }
    }
  });

  $("#remove_role_dropdown").on("change", function () {
    if ($(this).val() === "administrator") {
      alert("The Administrator role cannot be removed from users.");
      $(this).val("");
    }
  });

  // Temporary role assignment form
  $("#role_slug").on("change", function () {
    if ($(this).val() === "administrator") {
      alert("The Administrator role cannot be assigned as a temporary role.");
      $(this).val("");
    }
  });

  $("#bulk_role").on("change", function () {
    const bulkAction = $("#bulk_action").val();
    if ($(this).val() === "administrator" && bulkAction === "remove_role") {
      alert("The Administrator role cannot be removed via bulk action.");
      $(this).val("");
    }
  });

  $("#advausro-assign-temp-role-form").on("submit", function (e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append("action", "advausro_assign_temp_role");
    formData.append("security", advausro_ajax_object.nonce);
    formData.append("user_id", $("#user_id").val());
    formData.append("role_slug", $("#role_slug").val());
    formData.append("expires_at", $("#expires_at").val());

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          alert(response.data.message || "Temporary role assigned successfully.");
          location.reload();
        } else {
          alert("Error: " + (response.data?.message || "Failed to assign temporary role."));
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", error);
        alert("Request failed: " + error);
      }
    });
  });

  // Remove temporary role functionality
  $(document).on("click", ".remove-temp-role", function () {
    const roleId = $(this).data("role-id");

    if (!confirm("Are you sure you want to remove this temporary role?")) {
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "advausro_remove_temp_role",
        security: advausro_ajax_object.nonce,
        role_id: roleId
      },
      success: function (response) {
        if (response.success) {
          alert(response.data.message || "Temporary role removed successfully.");
          location.reload();
        } else {
          alert("Error: " + (response.data?.message || "Failed to remove temporary role."));
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", error);
        alert("Request failed: " + error);
      }
    });
  });

  // Countdown updater for temporary roles
  function updateCountdowns() {
    $(".expires-countdown").each(function () {
      const $el = $(this);
      const expiry = new Date($el.data("expires")).getTime();
      const now = new Date().getTime();
      const diff = expiry - now;

      if (diff <= 0) {
        $el.text("EXPIRED").css("color", "red");
      } else {
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        let timeString = "⏳ ";
        if (days > 0) timeString += `${days}d `;
        timeString += `${hours}h ${minutes}m ${seconds}s`;
        $el.text(timeString);
      }
    });
  }

  updateCountdowns();
  setInterval(updateCountdowns, 1000);

  // Periodic check for expired roles
  setInterval(function () {
    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "advausro_check_and_remove_expired_roles",
        security: advausro_ajax_object.nonce
      },
      success: function (response) {
        if (response.success && response.data.removed_ids && response.data.removed_ids.length > 0) {
          response.data.removed_ids.forEach(function (id) {
            $("#advausro-row-" + id).fadeOut(500, function () {
              $(this).remove();
            });
          });

          setTimeout(function () {
            location.reload();
          }, 1000);
        }
      },
      error: function (xhr, status, error) {
        console.error("Failed to check expired roles:", error);
      }
    });
  }, 30000);

  // Search functionality for capabilities
  $("#delete-search").on("keyup", function () {
    const searchTerm = $(this).val().toLowerCase();
    $("#delete-capabilities-list li").each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(searchTerm));
    });
  });

  // Search functionality for roles
  $("#delete-role-search").on("keyup", function () {
    const searchTerm = $(this).val().toLowerCase();
    $(".crm-delete-role ul li").each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(searchTerm));
    });
  });

  // Role display name to slug conversion
  $('input[name="role_display"]').on("input", function () {
    let displayName = $(this).val().trim();
    let slug = displayName
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(/[^a-z0-9-]/g, "")
      .replace(/-+/g, "-")
      .replace(/^-|-$/g, "");
    $('input[name="role_name"]').val(slug);
  });

  // Capabilities tab switching
  $(".cap-tab").off("click").on("click", function () {
    $(".cap-tab").removeClass("active");
    $(this).addClass("active");

    const section = $(this).data("section");
    $(".crm-capabilities").hide();
    $("#cap-" + section).show();
  });

  // Initialize capabilities display
  $(".crm-capabilities").hide();
  $("#cap-All").show();
  $(".cap-tab").first().addClass("active");

  // Capability search functionality
  $("#crm-cap-search").on("keyup", function () {
    const searchTerm = $(this).val().toLowerCase();
    
    // Search in current active section or search in All?
    // Let's search in all sections if searching, but respect the container visibility?
    // Actually, a global search that shows/hides items is best.
    
    if (searchTerm === "") {
        // Reset visibility
        $(".cap-item").show();
        $(".cap-section").show();
        return;
    }

    $(".cap-item").each(function () {
      const text = $(this).text().toLowerCase();
      $(this).toggle(text.includes(searchTerm));
    });

    // Hide empty sections in "All" tab
    $(".cap-section").each(function() {
        const visibleItems = $(this).find(".cap-item:visible").length;
        $(this).toggle(visibleItems > 0);
    });
  });

  // Sync duplicate capability checkboxes
  $(document).on("change", ".capability-checkbox", function () {
    const val = $(this).val();
    const isChecked = $(this).prop("checked");
    $('.capability-checkbox[value="' + val + '"]').prop("checked", isChecked);
  });

  // Role selector change handler
  $(".crm-role-selector").off("change").on("change", function () {
    const role = $(this).val();
    const protectedRoles = ["administrator", "shop_manager", "customer", "member", "wp_seo_manager", "wp_seo_editor", "bbp_keymaster"];

    if (role === "administrator") {
      alert("The Administrator role is a core role and cannot be modified here for security reasons.");
      $(this).val("");
      $(".capability-checkbox").prop("checked", false);
      return;
    }

    if (protectedRoles.includes(role)) {
      if (!confirm(`Warning: ${role} is a protected system role. Modifying its capabilities could affect site functionality. Do you want to continue?`)) {
        $(this).val("");
        $(".capability-checkbox").prop("checked", false);
        return;
      }
    }
    if (role) {
      $.ajax({
        url: advausro_ajax_object.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "advausro_fetch_role_capabilities",
          security: advausro_ajax_object.nonce,
          role: role
        },
        success: function (response) {
          $(".capability-checkbox").prop("checked", false);
          if (response.success && response.data.capabilities) {
            response.data.capabilities.forEach(function (cap) {
              $('.capability-checkbox[value="' + cap + '"]').prop("checked", true);
            });
          }
        },
        error: function (xhr, status, error) {
          console.error("Get Role Capabilities Error:", {
            status: status,
            error: error,
            response: xhr.responseText
          });
        }
      });
    } else {
      $(".capability-checkbox").prop("checked", false);
      const defaults = ['read', 'edit_posts', 'publish_posts', 'upload_files'];
      defaults.forEach(function(cap) {
           $('.capability-checkbox[value="' + cap + '"]').prop("checked", true);
      });
    }
  });

  // Populate role dropdown
  function populateRoleDropdown() {
    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_get_custom_roles",
        security: advausro_ajax_object.nonce
      },
      success: function (response) {
        if (response.success) {
          const $dropdown = $(".crm-role-selector");
          $dropdown.empty();
          if (response.data.roles.length === 0) {
            $dropdown.append('<option value="">No custom roles available</option>');
          } else {
            $dropdown.append('<option value="">Select a role</option>');
            response.data.roles.forEach(function (role) {
              $dropdown.append(
                $("<option></option>")
                  .val(role.role_slug)
                  .text(role.display_name)
              );
            });
          }
        } else {
          console.error("Failed to fetch custom roles:", response.data.message);
        }
      },
      error: function (xhr, status, error) {
        console.error("Fetch Roles Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
      }
    });
  }

  populateRoleDropdown();

  // Clone role functionality
  $(document).off("click", ".clone-role-btn").on("click", ".clone-role-btn", function () {
    const $button = $(this);
    const selectedRole = $(".crm-role-selector").val();
    const selectedRoleText = $(".crm-role-selector option:selected").text().trim();

    if (!selectedRole || !selectedRoleText) {
      alert("Please select a role to clone.");
      return;
    }

    const newRoleName = selectedRoleText + " (Clone)";
    $button.text("Cloning...").prop("disabled", true);

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_clone_role",
        role_slug: selectedRole,
        new_role_name: newRoleName,
        nonce: advausro_ajax_object.nonce
      },
      success: function (response) {
        $button.text("Clone Role").prop("disabled", false);

        if (response.success && response.data && response.data.role_slug && response.data.role_name) {
          const roleSlug = $("<div/>").text(response.data.role_slug).html();
          const roleName = $("<div/>").text(response.data.role_name).html();
          const capabilities = Array.isArray(response.data.capabilities) ? response.data.capabilities : [];

          const $newOption = $("<option></option>")
            .val(roleSlug)
            .text(roleName)
            .attr("data-capabilities", JSON.stringify(capabilities));

          $(".crm-role-selector").append($newOption).val(roleSlug).trigger("change");

          const $deleteList = $("#delete-roles-list");
          if ($deleteList.find('li:contains("No custom roles found")').length) {
            $deleteList.empty();
          }

          const newItem = $(
            '<li class="delete-role-item" style="display:none;">' +
            '<input type="checkbox" class="delete-checkbox" value="' + roleSlug + '" /> ' +
            roleName + " (" + roleSlug + ")" +
            "</li>"
          );
          $deleteList.append(newItem);
          newItem.fadeIn();

          alert("Role cloned successfully!");
        } else {
          alert("Error: " + (response.data && response.data.message ? response.data.message : "Unknown error occurred."));
        }
      },
      error: function (xhr, status, error) {
        console.error("Clone Role AJAX Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
        $button.text("Clone Role").prop("disabled", false);
        alert("AJAX error: " + status + " - " + error);
      }
    });
  });

  // Add new capability
  $("#add-capability-btn").off("click").on("click", function () {
    const newCapability = $("#new-capability").val().trim();

    if (!newCapability) {
      alert("Please enter a capability name.");
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_add_new_capability",
        security: advausro_ajax_object.nonce,
        capability: newCapability
      },
      success: function (response) {
        if (response.success) {
          const capability = response.data.capability;
          
          // 1. Add to Delete Capabilities list
          if ($('#delete-capabilities-list li:contains("No custom capabilities found")').length) {
            $("#delete-capabilities-list").empty();
          }
          $("#delete-capabilities-list").append(`
            <li>
              <input type="checkbox" class="delete-checkbox" value="${capability}"> 
              ${capability}
            </li>
          `);

          // 2. Add to "All" tab in the UI
          let $customSection = $("#cap-All .cap-section:contains('Custom')");
          if ($customSection.length === 0) {
              $customSection = $(`
                  <div class="cap-section">
                      <h3>Custom</h3>
                      <div class="cap-list"></div>
                  </div>
              `);
              $("#cap-All").append($customSection);
          }
          $customSection.find(".cap-list").append(`
                <div class="cap-item">
                    <label>
                        <input type="checkbox" class="capability-checkbox" value="${capability}">
                        ${capability}
                    </label>
                </div>
          `);

          // 3. Add to "Custom" tab/sidebar if not exists
          if ($(".cap-tab[data-section='Custom']").length === 0) {
              $(".crm-sidebar ul").append('<li class="cap-tab" data-section="Custom">Custom</li>');
              $(".capabilities-wrapper").append(`
                  <div class="crm-capabilities" id="cap-Custom" style="display:none;">
                      <h3>Custom</h3>
                      <div class="cap-list"></div>
                  </div>
              `);
          }
          $("#cap-Custom .cap-list").append(`
                <div class="cap-item">
                    <label>
                        <input type="checkbox" class="capability-checkbox" value="${capability}">
                        ${capability}
                    </label>
                </div>
          `);

          // Automatically check the new capability for the user
          $(`.capability-checkbox[value="${capability}"]`).prop("checked", true);
          
          alert(response.data.message);
          $("#new-capability").val("");
        } else {
          alert("Error: " + (response.data.message || "Failed to add capability."));
        }
      },
      error: function (xhr, status, error) {
        console.error("Add Capability Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
        alert("Error adding capability. Please try again.");
      }
    });
  });

  // Add new role
  $("#add-new-role-btn").off("click").on("click", function (e) {
    e.preventDefault();

    const roleName = $('input[name="role_name"]').val().trim();
    const roleDisplay = $('input[name="role_display"]').val().trim();

    if (!roleName || !roleDisplay) {
      alert("Role Name and Display Name are required.");
      return;
    }

    const selectedCapabilities = $(".capability-checkbox:checked").map(function () {
      return $(this).val();
    }).get();

    // Ensure unique capabilities
    const uniqueCapabilities = selectedCapabilities.filter((v, i, a) => a.indexOf(v) === i);

    if (uniqueCapabilities.length === 0) {
      alert("Please select at least one capability.");
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_add_new_custom_role",
        security: advausro_ajax_object.nonce,
        role_name: roleName,
        role_display: roleDisplay,
        capabilities: uniqueCapabilities
      },
      success: function (response) {
        if (response.success) {
          const roleSlug = response.data.role_slug;
          const displayName = response.data.display_name;

          const $selector = $(".crm-role-selector");
          const newOption = $("<option>", {
            value: roleSlug,
            text: displayName
          });
          $selector.append(newOption);
          $selector.val(roleSlug);

          const $deleteList = $("#delete-roles-list");
          if ($deleteList.find('li:contains("No custom roles found")').length) {
            $deleteList.empty();
          }

          const newItem = $("<li>").append(
            $("<input>", {
              type: "checkbox",
              class: "delete-checkbox",
              value: roleSlug
            }),
            document.createTextNode(" " + displayName + " (" + roleSlug + ")")
          );
          $deleteList.append(newItem);
          newItem.hide().fadeIn();

          $('input[name="role_name"]').val("");
          $('input[name="role_display"]').val("");
          $(".capability-checkbox").prop("checked", false);

          alert("Role added successfully!");
          $(".crm-role-selector").trigger("change");
        } else {
          alert("Error: " + (response.data.message || "Failed to add role."));
        }
      },
      error: function (xhr, status, error) {
        console.error("Add Role Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
        alert("Error adding role. Please try again.");
      }
    });
  });


  $("#add_role_submit").on("click", function (e) {

    var role = $("#add_role_dropdown").val();

    if (role === "administrator") {

      let confirmAdd = confirm("You are adding the Administrator role. This role has full access and should be assigned carefully. Do you want to continue?");

      if (!confirmAdd) {
        e.preventDefault(); // stop form submission
      }
    }

  });


  // Delete capabilities
  $(".delete-btn").off("click").on("click", function () {
    const capabilitiesToDelete = [];
    $("#delete-capabilities-list .delete-checkbox:checked").each(function () {
      capabilitiesToDelete.push($(this).val());
    });

    if (capabilitiesToDelete.length === 0) {
      alert("Please select capabilities to delete.");
      return;
    }

    if (!confirm("Are you sure you want to delete the selected capabilities? This action cannot be undone.")) {
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_delete_custom_capabilities",
        security: advausro_ajax_object.nonce,
        capabilities: JSON.stringify(capabilitiesToDelete)
      },
      beforeSend: function () {
        $(".delete-btn").text("Deleting...").prop("disabled", true);
      },
      success: function (response) {
        $(".delete-btn").text("Delete").prop("disabled", false);

        if (response.success) {
          capabilitiesToDelete.forEach(function (capability) {
            $('.delete-checkbox[value="' + capability + '"]').closest("li").fadeOut(400, function () {
              $(this).remove();
            });
          });

          capabilitiesToDelete.forEach(function (capability) {
            $('#cap-Custom .capability-checkbox[value="' + capability + '"]').closest(".cap-item").remove();
          });

          if ($("#delete-capabilities-list li").length === 0) {
            $("#delete-capabilities-list").html("<li>No custom capabilities found.</li>");
          }

          if ($("#cap-Custom .cap-item").length === 0) {
            $('.crm-sidebar li[data-section="Custom"]').remove();
            $("#cap-Custom").remove();
          }

          $("#delete-search").val("");
          $(".crm-role-selector").trigger("change");
          alert(response.data.message);
        } else {
          alert("Error: " + (response.data.message || "Failed to delete capabilities."));
        }
      },
      error: function (xhr, status, error) {
        $(".delete-btn").text("Delete").prop("disabled", false);
        console.error("Delete Capabilities Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
        alert("Error deleting capabilities. Please try again.");
      }
    });
  });

  // Delete roles
  $("#delete-role-btn").off("click").on("click", function () {
    const rolesToDelete = [];
    const protectedRoles = ["administrator", "shop_manager", "customer", "member", "wp_seo_manager", "wp_seo_editor", "bbp_keymaster"];

    $("#delete-roles-list .delete-checkbox:checked").each(function () {
      const role = $(this).val();
      if (protectedRoles.includes(role)) {
        alert(`The ${role} role is a protected system role and cannot be deleted.`);
        $(this).prop("checked", false);
        return; // Skip this one
      }
      rolesToDelete.push(role);
    });

    if (rolesToDelete.length === 0) {
      if ($("#delete-roles-list .delete-checkbox:checked").length === 0) {
        alert("Please select at least one role to delete.");
      }
      return;
    }

    if (!confirm("Are you sure you want to delete the selected roles? This action cannot be undone!")) {
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_delete_custom_roles",
        security: advausro_ajax_object.nonce,
        roles: JSON.stringify(rolesToDelete)
      },
      beforeSend: function () {
        $("#delete-role-btn").text("Deleting...").prop("disabled", true);
      },
      success: function (response) {
        $("#delete-role-btn").text("Delete").prop("disabled", false);

        if (response.success) {
          rolesToDelete.forEach(function (roleSlug) {
            $('.delete-checkbox[value="' + roleSlug + '"]').closest("li").fadeOut(400, function () {
              $(this).remove();
            });
          });

          if ($("#delete-roles-list li").length === 0) {
            $("#delete-roles-list").html("<li>No custom roles found.</li>");
          }

          $("#delete-role-search").val("");
          populateRoleDropdown();
          alert(response.data.message);
        } else {
          alert("Error: " + (response.data.message || "Failed to delete roles."));
        }
      },
      error: function (xhr, status, error) {
        $("#delete-role-btn").text("Delete").prop("disabled", false);
        console.error("Delete Role Error:", {
          status: status,
          error: error,
          response: xhr.responseText
        });
        alert("Error deleting roles. Please try again.");
      }
    });
  });

  // Update role capabilities
  $(document).off("click", ".update-btn").on("click", ".update-btn", function (e) {
    e.preventDefault();

    const roleKey = $(".crm-role-selector").val();
    const protectedRoles = ["administrator", "shop_manager", "customer", "member", "wp_seo_manager", "wp_seo_editor", "bbp_keymaster"];

    if (protectedRoles.includes(roleKey) && !confirm(`Confirm Update: You are about to modify the protected role "${roleKey}". Are you sure?`)) {
      return;
    }

    const selectedCaps = $(".capability-checkbox:checked").map(function () {
      return $(this).val();
    }).get();

    // Ensure unique capabilities
    const uniqueCaps = selectedCaps.filter((v, i, a) => a.indexOf(v) === i);

    if (!roleKey) {
      alert("Please select a role to update capabilities.");
      return;
    }

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "advausro_update_role_capabilities",
        role_slug: roleKey,
        capabilities: uniqueCaps,
        nonce: advausro_ajax_object.nonce
      },
      beforeSend: function () {
        $(".update-btn").text("Updating...").prop("disabled", true);
      },
      success: function (response) {
        $(".update-btn").text("Update").prop("disabled", false);

        if (response.success) {
          alert("Capabilities updated successfully.");
          location.reload();
        } else {
          alert("Error: " + response.data.message);
        }
      },
      error: function () {
        alert("Failed to update capabilities.");
        $(".update-btn").text("Update").prop("disabled", false);
      }
    });
  });

  // Handle OAuth Provider selection and presets
  $("#advausro_provider").on("change", function () {
    const provider = $(this).val();
    const $authUrl = $("#authorization_url");
    const $tokenUrl = $("#token_url");
    const $userInfoUrl = $("#user_info_url");

    const presets = {
      google: {
        auth: "https://accounts.google.com/o/oauth2/v2/auth",
        token: "https://oauth2.googleapis.com/token",
        userinfo: "https://www.googleapis.com/oauth2/v3/userinfo"
      },
      microsoft: {
        auth: "https://login.microsoftonline.com/common/oauth2/v2.0/authorize",
        token: "https://login.microsoftonline.com/common/oauth2/v2.0/token",
        userinfo: "https://graph.microsoft.com/oidc/userinfo"
      }
    };

    if (presets[provider]) {
      $authUrl.val(presets[provider].auth).prop("readonly", true).css("background-color", "#f0f0f1");
      $tokenUrl.val(presets[provider].token).prop("readonly", true).css("background-color", "#f0f0f1");
      $userInfoUrl.val(presets[provider].userinfo).prop("readonly", true).css("background-color", "#f0f0f1");
      alert(provider.charAt(0).toUpperCase() + provider.slice(1) + " presets loaded. The redirect URL is pre-filled, please ensure it matches your app settings.");
    } else {
      // Custom provider
      $authUrl.prop("readonly", false).css("background-color", "");
      $tokenUrl.prop("readonly", false).css("background-color", "");
      $userInfoUrl.prop("readonly", false).css("background-color", "");
    }
  });

  // Initialize preset field states on load
  if ($("#advausro_provider").val() !== "custom") {
    $("#authorization_url, #token_url, #user_info_url").prop("readonly", true).css("background-color", "#f0f0f1");
  }

  // Legacy button handler (can be removed if button is removed from UI, but keeping for compatibility)
  $("#advausro-load-google-presets").on("click", function (e) {
    e.preventDefault();
    $("#advausro_provider").val("google").trigger("change");
  });

  // Select all functionality fallback/improvement
  $(document).on("change", "#advausro-select-all", function () {
    const isChecked = $(this).prop("checked");
    const $checkboxes = $(".user_checkbox");

    if (isChecked) {
      // Check if any protected role is present among the visible rows
      let hasProtected = false;
      const protectedRoles = ["administrator", "shop_manager", "customer", "member", "wp_seo_manager", "wp_seo_editor", "bbp_keymaster"];

      $checkboxes.each(function () {
        if ($(this).is(':visible')) {
          const roles = ($(this).data("roles") || "").split(",");
          if (roles.some(role => protectedRoles.includes(role))) {
            hasProtected = true;
            return false;
          }
        }
      });

      if (hasProtected) {
        if (!confirm("Warning: You are selecting users with protected system roles. Are you sure you want to proceed?")) {
          $(this).prop("checked", false);
          return;
        }
      }
    }

    window.advausro_bulk_checking = true;
    $checkboxes.each(function () {
      if ($(this).is(':visible')) {
        $(this).prop("checked", isChecked).trigger("change");
      }
    });
    window.advausro_bulk_checking = false;
  });

  // Bulk action form submission
  $(document).on("click", "#do-bulk-action", function (e) {
    e.preventDefault();
    const action = $("#bulk_action").val();
    const role = $("#bulk_role").val();
    const expiry = $("#bulk_expiry").val();
    const user_ids = $(".user_checkbox:checked").map(function () {
      return this.value;
    }).get();

    if (!action || user_ids.length === 0) {
      alert("Please select an action and at least one user.");
      return;
    }

    if (action === "remove_role" && ["administrator", "shop_manager"].includes(role)) {
      alert(`The ${role} role is protected and cannot be removed from users.`);
      return;
    }

    if (action === "delete" && !confirm("Are you sure you want to delete the selected users? This action cannot be undone.")) {
      return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true).text("Processing...");

    $.ajax({
      url: advausro_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "advausro_bulk_action",
        security: advausro_ajax_object.nonce,
        bulk_action: action,
        role_slug: role,
        expires_at: expiry,
        user_ids: user_ids
      },
      success: function (response) {
        if (response.success) {
          alert(response.data.message || "Action processed successfully.");
          location.reload();
        } else {
          alert("Error: " + (response.data?.message || "Action failed."));
        }
      },
      error: function () {
        alert("Request failed. Please try again.");
      },
      complete: function () {
        $btn.prop("disabled", false).text("Apply");
      }
    });
  });

  // User deletion/modification checkbox confirmation for Administrator role
  $(document).on("change", ".user_checkbox", function () {
    if ($(this).is(":checked")) {
      const roles = $(this).data("roles") || "";
      if (roles.split(",").includes("administrator")) {
        // Only show confirm if this wasn't triggered by a bulk action check
        if (!window.advausro_bulk_checking) {
          if (!confirm("Warning: You are selecting an Administrator. Are you sure you want to proceed?")) {
            $(this).prop("checked", false);
          }
        }
      }
    }
  });

  // Toggle all checkboxes helper function (kept for compatibility)
  window.toggleAll = function (source) {
    $("#advausro-select-all").prop("checked", source.checked).trigger("change");
  };

  // Log search functionality
  let searchTimer;
  $("#advausro-log-search-input").on("input", function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      const searchTerm = $("#advausro-log-search-input").val();
      const eventType = $('#advausro-log-filter-form select[name="event_type"]').val();
      const startDate = $('#advausro-log-filter-form input[name="start_date"]').val();
      const endDate = $('#advausro-log-filter-form input[name="end_date"]').val();

      $.ajax({
        url: advausro_ajax_object.ajax_url,
        method: "POST",
        data: {
          action: "advausro_search_logs",
          s: searchTerm,
          event_type: eventType,
          start_date: startDate,
          end_date: endDate,
          filter_nonce: advausro_ajax_object.nonce
        },
        success: function (response) {
          if (response.success) {
            $("#advausro-log-table-container").html(response.data.table);
          } else {
            console.error("Error:", response.data.message);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
        }
      });
    }, 300);
  });

  // Filter changes
  $("#event-type-filter, #start-date-filter, #end-date-filter").on("change", function () {
    $("#advausro-log-search-input").trigger("input");
  });

  // Bulk actions for logs
  const config = {
    messages: advausro_ajax_object.messages || {
      confirm_delete: "Are you sure you want to delete the selected log entries? This action cannot be undone.",
      select_action: "Please select an action.",
      select_items: "Please select at least one log entry.",
      processing: "Processing..."
    },
    nonce: advausro_ajax_object.nonce || "",
    bulk_nonce: advausro_ajax_object.bulk_nonce || ""
  };

  function initBulkActions() {
    $("#advausro-log-table-form").on("submit", handleBulkFormSubmission);
    initSelectAllCheckboxes();
    $('input[name="bulk-delete[]"]').on("change", updateSelectAllState);
    initSearchFunctionality();
    initKeyboardShortcuts();
  }

  function handleBulkFormSubmission(e) {
    const $form = $(this);
    const action = getBulkAction();
    const checkedBoxes = getCheckedLogIds();

    if (!isValidAction(action)) {
      e.preventDefault();
      showAlert(config.messages.select_action);
      return false;
    }

    if (checkedBoxes.length === 0) {
      e.preventDefault();
      showAlert(config.messages.select_items);
      return false;
    }

    if ((action === "delete" || action === "bulk_delete") && !confirmDestructiveAction()) {
      e.preventDefault();
      return false;
    }

    showLoadingState($form);
    return true;
  }

  function getBulkAction() {
    const topAction = $("#bulk-action-selector-top").val();
    const bottomAction = $("#bulk-action-selector-bottom").val();
    return topAction && topAction !== "-1" ? topAction : bottomAction && bottomAction !== "-1" ? bottomAction : "";
  }

  function getCheckedLogIds() {
    return $('input[name="bulk-delete[]"]:checked').map(function () {
      return parseInt($(this).val());
    }).get();
  }

  function isValidAction(action) {
    const validActions = ["bulk_delete", "bulk_export"];
    return action && action !== "-1" && validActions.includes(action);
  }

  function confirmDestructiveAction() {
    return confirm(config.messages.confirm_delete);
  }

  function showLoadingState($form) {
    $form.find('#doaction, #doaction2').prop("disabled", true).text(config.messages.processing);
    $form.find(".bulkactions").append('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>');
  }

  function initSelectAllCheckboxes() {
    $("#cb-select-all-1, #cb-select-all-2").on("change", function () {
      const isChecked = $(this).prop("checked");
      $('input[name="bulk-delete[]"]').prop("checked", isChecked);
      $("#cb-select-all-1, #cb-select-all-2").prop("checked", isChecked);
      updateBulkActionState();
    });
  }

  function updateSelectAllState() {
    const $allCheckboxes = $('input[name="bulk-delete[]"]');
    const $checkedBoxes = $allCheckboxes.filter(":checked");
    const $selectAllBoxes = $("#cb-select-all-1, #cb-select-all-2");

    if ($checkedBoxes.length === 0) {
      $selectAllBoxes.prop("checked", false).prop("indeterminate", false);
    } else if ($checkedBoxes.length === $allCheckboxes.length) {
      $selectAllBoxes.prop("checked", true).prop("indeterminate", false);
    } else {
      $selectAllBoxes.prop("checked", false).prop("indeterminate", true);
    }

    updateBulkActionState();
  }

  function updateBulkActionState() {
    const hasSelection = $('input[name="bulk-delete[]"]:checked').length > 0;
    const $bulkSelectors = $("#bulk-action-selector-top, #bulk-action-selector-bottom");
    const $submitButtons = $("#doaction, #doaction2");

    if (hasSelection) {
      $bulkSelectors.removeClass("disabled");
      $submitButtons.removeClass("disabled");
    } else {
      $bulkSelectors.addClass("disabled");
      $submitButtons.addClass("disabled");
    }
  }

  function initSearchFunctionality() {
    const $searchInput = $("#log-search-input");
    if ($searchInput.length) {
      let searchTimeout;
      $searchInput.on("input", function () {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();
        searchTimeout = setTimeout(function () {
          filterTableRows(searchTerm);
        }, 300);
      });
    }
  }

  function filterTableRows(searchTerm) {
    const $tableRows = $("#advausro-log-table-form tbody tr");
    let visibleCount = 0;

    $tableRows.each(function () {
      const $row = $(this);
      const rowText = $row.text().toLowerCase();

      if (!searchTerm || rowText.includes(searchTerm)) {
        $row.show();
        visibleCount++;
      } else {
        $row.hide();
        $row.find('input[type="checkbox"]').prop("checked", false);
      }
    });

    updateSelectAllState();
    toggleNoResultsMessage(visibleCount === 0 && searchTerm);
  }

  function toggleNoResultsMessage(show) {
    const $noResults = $("#no-results-message");
    if (show) {
      if ($noResults.length === 0) {
        $("#advausro-log-table-form tbody").append(
          '<tr id="no-results-message"><td colspan="6" style="text-align: center; padding: 20px; color: #666;">No matching log entries found.</td></tr>'
        );
      }
    } else {
      $noResults.remove();
    }
  }

  function initKeyboardShortcuts() {
    $(document).on("keydown", function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === "a" && !$(e.target).is("input, textarea")) {
        e.preventDefault();
        $("#cb-select-all-1").prop("checked", true).trigger("change");
      }

      if (e.key === "Delete" && !$(e.target).is("input, textarea")) {
        const checkedCount = $('input[name="bulk-delete[]"]:checked').length;
        if (checkedCount > 0) {
          $("#bulk-action-selector-top").val("bulk_delete");
          if (confirmDestructiveAction()) {
            $("#advausro-log-table-form").trigger("submit");
          }
        }
      }
    });
  }

  function showAlert(message) {
    if (typeof wp !== "undefined" && wp.notices) {
      wp.notices.create({
        message: message,
        type: "warning",
        isDismissible: true
      });
    } else {
      alert(message);
    }
  }

  function hideLoadingState($form) {
    $form.find('#doaction, #doaction2').prop("disabled", false).text("Apply");
    $form.find(".spinner").remove();
  }

  function initRowEffects() {
    $("#advausro-log-table-form tbody tr").hover(
      function () {
        $(this).addClass("hover");
      },
      function () {
        $(this).removeClass("hover");
      }
    );
  }

  function addSelectionCounter() {
    if ($("#selection-counter").length === 0) {
      $(".tablenav.top .bulkactions").append(
        '<span id="selection-counter" style="margin-left: 10px; color: #666;"></span>'
      );
    }

    $(document).on("change", 'input[name="bulk-delete[]"], #cb-select-all-1, #cb-select-all-2', function () {
      const count = $('input[name="bulk-delete[]"]:checked').length;
      const total = $('input[name="bulk-delete[]"]').length;
      $("#selection-counter").text(count > 0 ? `${count} of ${total} selected` : "");
    });
  }

  // Initialize bulk actions
  initBulkActions();
  initRowEffects();
  addSelectionCounter();
  updateSelectAllState();
  updateBulkActionState();

  // Timezone modal functionality
  if ($("#advausro-timezone-modal").length) {
    $("#advausro-timezone-modal").fadeIn(300);

    $("#advausro-timezone-form").on("submit", function (e) {
      e.preventDefault();
      const timezone = $("#advausro-timezone").val();

      if (!timezone) {
        alert("Please select a timezone.");
        return;
      }

      $.ajax({
        url: advausro_timezone_object.ajax_url,
        type: "POST",
        data: {
          action: "advausro_update_timezone",
          nonce: advausro_timezone_object.nonce,
          timezone: timezone
        },
        success: function (response) {
          alert(response.data.message);
          $("#advausro-timezone-modal").fadeOut(300, function () {
            if (response.data.redirect) {
              window.location.href = response.data.redirect;
            }
          });
        },
        error: function (error) {
          const errorMessage = error.responseJSON && error.responseJSON.data && error.responseJSON.data.message
            ? error.responseJSON.data.message
            : "An unexpected error occurred. Please try again.";
          alert("Error: " + errorMessage);
        }
      });
    });

    $("#advausro-skip-timezone").on("click", function () {
      $.ajax({
        url: advausro_timezone_object.ajax_url,
        type: "POST",
        data: {
          action: "advausro_update_timezone",
          nonce: advausro_timezone_object.nonce,
          timezone: ""
        },
        success: function (response) {
          $("#advausro-timezone-modal").fadeOut(300, function () {
            if (response.data.redirect) {
              window.location.href = response.data.redirect;
            }
          });
        },
        error: function (error) {
          $("#advausro-timezone-modal").fadeOut(300, function () {
            window.location.href = advausro_timezone_object.redirect_url;
          });
        }
      });
    });
  }

  // Help panel functionality
  const panel = document.getElementById("help-panel");
  const overlay = document.getElementById("help-overlay");
  const openBtn = document.getElementById("open-help-panel");
  const closeBtn = document.getElementById("close-help-panel");

  if (openBtn) {
    openBtn.addEventListener("click", function (e) {
      e.preventDefault();
      panel.classList.add("open");
      overlay.classList.add("active");
      panel.setAttribute("tabindex", "-1");
      panel.focus();
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", closePanel);
  }

  if (overlay) {
    overlay.addEventListener("click", closePanel);
  }

  function closePanel() {
    panel.classList.remove("open");
    overlay.classList.remove("active");
    if (openBtn) openBtn.focus();
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && panel.classList.contains("open")) {
      closePanel();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        closePanel();
      }
    });
  }

  // Initialize role selector
  $(".crm-role-selector").trigger("change");
});
