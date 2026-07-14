jQuery(document).ready(function ($) {
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
          timezone: timezone,
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
          const errorMessage =
            error.responseJSON &&
            error.responseJSON.data &&
            error.responseJSON.data.message
              ? error.responseJSON.data.message
              : "An unexpected error occurred. Please try again.";
          alert("Error: " + errorMessage);
        },
      });
    });

    $("#advausro-skip-timezone").on("click", function () {
      $.ajax({
        url: advausro_timezone_object.ajax_url,
        type: "POST",
        data: {
          action: "advausro_update_timezone",
          nonce: advausro_timezone_object.nonce,
          timezone: "",
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
        },
      });
    });
  }
});
