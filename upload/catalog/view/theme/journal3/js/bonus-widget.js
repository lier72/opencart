/**
 * Bonus Widget JavaScript
 * Handles bonus point application and cancellation in cart/checkout
 */
(function($) {
  'use strict';

  // Initialize when DOM is ready
  $(document).ready(function() {
    initBonusWidget();
  });

  // Also initialize when called directly (for dynamic content)
  window.initBonusWidget = function() {
    console.log('Initializing bonus widget');

    // Inject styles if not already present
    if (!$('#bonus-flip-styles').length) {
      $('head').append(`
        <style id="bonus-flip-styles">
          .bonus-flip-container {
            perspective: 1000px;
            margin: 15px 0;
            position: relative;
          }

          .bonus-flip-card {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s ease;
            transform-style: preserve-3d;
          }

          .bonus-flip-card.flipped {
            transform: rotateY(180deg);
          }

          .bonus-card-front,
          .bonus-card-back {
            width: 100%;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
          }

          .bonus-card-front {
            transform: rotateY(0deg);
          }

          .bonus-card-back {
            transform: rotateY(180deg);
            display: none !important;
          }

          .bonus-flip-card.flipped .bonus-card-front {
            display: none !important;
          }

          .bonus-flip-card.flipped .bonus-card-back {
            display: block !important;
          }
        </style>
      `);
    }

    // Remove existing event handlers to prevent duplicates
    $('#button-reward-cart').off('click');
    $('#button-reward-cancel').off('click');
    $('#input-reward-cart').off('focus keypress input');
    $('#btn-show-spend').off('click');
    $('#btn-show-earn').off('click');

    // Flip to spending side
    $('#btn-show-spend').on('click', function() {
      $('#bonus-flip-card').addClass('flipped');
      // Focus on input after flip animation completes
      setTimeout(function() {
        $('#input-reward-cart').focus();
      }, 600);
    });

    // Flip back to earning side
    $('#btn-show-earn').on('click', function() {
      $('#bonus-flip-card').removeClass('flipped');
    });

    // Apply bonus button
    $('#button-reward-cart').on('click', function() {
      console.log('Apply button clicked!');

      var inputVal = $('#input-reward-cart').val();
      var rewardAmount = parseInt(inputVal);
      var maxAllowed = parseInt($('#input-reward-cart').attr('data-max'));

      // Check if field is empty or contains only whitespace
      if (!inputVal || inputVal.trim() === '') {
        alert('Пожалуйста, введите количество бонусов для использования');
        return;
      }

      // Check if it's not a valid number
      if (isNaN(rewardAmount)) {
        alert('Пожалуйста, введите корректное число');
        return;
      }

      // Check maximum limit
      if (rewardAmount > maxAllowed) {
        alert('Максимально можно использовать ' + maxAllowed + ' бонусов');
        return;
      }

      // Determine current page
      var isCheckoutPage = window.location.href.indexOf('checkout/checkout') !== -1;

      // On checkout page, use Journal3's Vue instance to update without reload
      if (isCheckoutPage && window._QuickCheckout && window._QuickCheckout.reward !== undefined) {
        $.ajax({
          url: 'index.php?route=extension/total/reward/reward',
          type: 'post',
          data: 'reward=' + rewardAmount,
          dataType: 'json',
          beforeSend: function() {
            // Show loading overlay like Journal3 does
            if (typeof window.loader === 'function') {
              loader('.quick-checkout-wrapper', true);
            }
            $('#button-reward-cart').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Применяем...');
          },
          complete: function() {
            $('#button-reward-cart').prop('disabled', false).html('Применить');
          },
          success: function(json) {
            if (json['error']) {
              // Hide loading on error
              if (typeof window.loader === 'function') {
                loader('.quick-checkout-wrapper', false);
              }
              alert(json['error']);
            } else {
              // Update Journal3's Vue instance and trigger save (loading will hide when save completes)
              window._QuickCheckout.reward = rewardAmount;
              window._QuickCheckout.save();
            }
          },
          error: function(xhr, ajaxOptions, thrownError) {
            // Hide loading on error
            if (typeof window.loader === 'function') {
              loader('.quick-checkout-wrapper', false);
            }
            $('#button-reward-cart').prop('disabled', false).html('Применить');
            alert('Ошибка: ' + thrownError);
          }
        });
      } else {
        // On cart page, do full page reload
        $.ajax({
          url: 'index.php?route=extension/total/reward/reward',
          type: 'post',
          data: 'reward=' + rewardAmount + (isCheckoutPage ? '&redirect=checkout/checkout' : ''),
          dataType: 'json',
          beforeSend: function() {
            $('#button-reward-cart').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Применяем...');
          },
          complete: function() {
            $('#button-reward-cart').prop('disabled', false).html('Применить');
          },
          success: function(json) {
            $('.alert-dismissible').remove();

            if (json['error']) {
              $('.breadcrumb').after('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
              $('html, body').animate({ scrollTop: 0 }, 'slow');
            }

            if (json['redirect']) {
              location = json['redirect'];
            }
          },
          error: function(xhr, ajaxOptions, thrownError) {
            $('#button-reward-cart').prop('disabled', false).html('Применить');
            alert('Ошибка: ' + thrownError);
          }
        });
      }
    });

    // Filter input to allow only numbers
    $('#input-reward-cart').on('input', function() {
      var value = $(this).val();
      // Remove any non-numeric characters
      var filtered = value.replace(/[^0-9]/g, '');
      if (value !== filtered) {
        $(this).val(filtered);
      }
    });

    // Quick apply max bonuses on focus
    $('#input-reward-cart').on('focus', function() {
      if ($(this).val() == '') {
        var maxAllowed = $(this).attr('data-max');
        $(this).attr('placeholder', 'Максимум: ' + maxAllowed);
      }
    });

    // Allow Enter key to submit
    $('#input-reward-cart').on('keypress', function(e) {
      if (e.which == 13) {
        $('#button-reward-cart').click();
        return false;
      }
    });

    // Cancel/Clear bonuses button
    $('#button-reward-cancel').on('click', function() {
      if (!confirm('Отменить использование бонусов?')) {
        return;
      }

      // Determine current page
      var isCheckoutPage = window.location.href.indexOf('checkout/checkout') !== -1;

      // On checkout page, use Journal3's Vue instance to update without reload
      if (isCheckoutPage && window._QuickCheckout && window._QuickCheckout.reward !== undefined) {
        $.ajax({
          url: 'index.php?route=extension/total/reward/reward',
          type: 'post',
          data: 'reward=0',
          dataType: 'json',
          beforeSend: function() {
            // Show loading overlay like Journal3 does
            if (typeof window.loader === 'function') {
              loader('.quick-checkout-wrapper', true);
            }
            $('#button-reward-cancel').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
          },
          complete: function() {
            $('#button-reward-cancel').prop('disabled', false).html('<i class="fa fa-times"></i>');
          },
          success: function(json) {
            // Update Journal3's Vue instance and trigger save (loading will hide when save completes)
            window._QuickCheckout.reward = 0;
            $('#input-reward-cart').val('');
            window._QuickCheckout.save();
            // Flip back to earning side
            $('#bonus-flip-card').removeClass('flipped');
          },
          error: function(xhr, ajaxOptions, thrownError) {
            // Hide loading on error
            if (typeof window.loader === 'function') {
              loader('.quick-checkout-wrapper', false);
            }
            $('#button-reward-cancel').prop('disabled', false).html('<i class="fa fa-times"></i>');
            alert('Ошибка: ' + thrownError);
          }
        });
      } else {
        // On cart page, do full page reload
        $.ajax({
          url: 'index.php?route=extension/total/reward/reward',
          type: 'post',
          data: 'reward=0' + (isCheckoutPage ? '&redirect=checkout/checkout' : ''),
          dataType: 'json',
          beforeSend: function() {
            $('#button-reward-cancel').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
          },
          complete: function() {
            $('#button-reward-cancel').prop('disabled', false).html('<i class="fa fa-times"></i>');
          },
          success: function(json) {
            // Flip back to earning side before redirect
            $('#bonus-flip-card').removeClass('flipped');

            if (json['redirect']) {
              location = json['redirect'];
            } else {
              // If no redirect, just reload the page to clear session
              location.reload();
            }
          },
          error: function(xhr, ajaxOptions, thrownError) {
            $('#button-reward-cancel').prop('disabled', false).html('<i class="fa fa-times"></i>');
            alert('Ошибка: ' + thrownError);
          }
        });
      }
    });

    console.log('Bonus widget initialized');
  };

})(jQuery);
