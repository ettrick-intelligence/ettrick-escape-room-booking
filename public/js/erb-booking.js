/* global ERB, eerbPublic, Stripe, jQuery */
(function ($) {
    'use strict';

    ERB.Booking = {

        // ── State ─────────────────────────────────────────────────────────────
        state:        null,   // from sessionStorage
        gameData:     null,   // from eerbPublic.gamesData
        selectedPlayers: 0,
        pricePence:   0,
        discountPct:  0,
        promoId:      null,
        totalPence:   0,
        customerId:   null,   // set after login
        tab:          'guest',
        timer:        null,
        stripe:       null,
        cardElement:  null,

        // ── Init ──────────────────────────────────────────────────────────────

        init: function () {
            if (!$('#erb-booking-app').length) return;

            var state = ERB.BookingState.get();

            if (!state || !state.gameId || !state.slotStart) {
                ERB.Booking.showPanel('no-state');
                return;
            }

            ERB.Booking.state    = state;
            ERB.Booking.gameData = (eerbPublic.gamesData && eerbPublic.gamesData[parseInt(state.gameId, 10)]) || null;

            // Resume existing hold or place a new one
            var holdExpires = state.holdExpires ? new Date(state.holdExpires) : null;
            var now         = new Date();
            var remaining   = holdExpires ? Math.floor((holdExpires - now) / 1000) : 0;

            if (remaining > 10) {
                // Valid hold already exists — resume with remaining time
                ERB.Booking.showPanel('1');
                ERB.Booking.renderSlotSummary();
                ERB.Booking.startTimerSeconds(remaining);
            } else {
                // Place a fresh hold
                ERB.Booking.holdSlot(function () {
                    ERB.Booking.showPanel('1');
                    ERB.Booking.renderSlotSummary();
                    ERB.Booking.startTimer();
                });
            }

            // Create-account checkbox toggle
            $('#erb-create-account').on('change', function () {
                $('#erb-create-password-wrap').toggle($(this).is(':checked'));
            });
        },

        // ── Slot hold ─────────────────────────────────────────────────────────

        holdSlot: function (onSuccess) {
            ERB.Booking.showPanel('loading');
            ERB.ajax('eerb_hold_slot', {
                game_id:     ERB.Booking.state.gameId,
                slot_start:  ERB.Booking.state.slotStart,
                session_key: ERB.Booking.state.sessionKey,
            }, function (data) {
                ERB.BookingState.set({ holdExpires: data.expires_at });
                onSuccess();
            }, function (msg) {
                // Slot no longer available
                ERB.Booking.showPanel('no-state');
                $('#erb-step-no-state h2').text('Slot No Longer Available');
                $('#erb-step-no-state p').text(msg || 'Sorry, that slot was just taken. Please choose another time.');
            });
        },

        // ── Timer ─────────────────────────────────────────────────────────────

        startTimer: function () {
            var mins = parseInt(eerbPublic.holdMinutes, 10) || 15;
            ERB.Booking.startTimerSeconds(mins * 60);
        },

        startTimerSeconds: function (seconds) {
            if (ERB.Booking.timer) ERB.Booking.timer.stop();
            $('#erb-timer-bar').show();
            ERB.Booking.timer = ERB.startTimer(
                $('#erb-timer-display'),
                seconds,
                ERB.Booking.onTimerExpire
            );
        },

        onTimerExpire: function () {
            // Release hold and send user back
            ERB.ajax('eerb_release_hold', {
                game_id:    ERB.Booking.state.gameId,
                slot_start: ERB.Booking.state.slotStart,
                session_key:ERB.Booking.state.sessionKey,
            }, function () {}, function () {});

            ERB.BookingState.clear();
            ERB.Booking.showPanel('no-state');
            $('#erb-step-no-state h2').text('Time Expired');
            $('#erb-step-no-state p').text('Your reserved slot has been released. Please return to the calendar to choose a new time.');
        },

        // ── Panel visibility ──────────────────────────────────────────────────

        showPanel: function (name) {
            $('#erb-step-loading, #erb-step-no-state, #erb-step-1, #erb-step-2, #erb-step-3, #erb-step-success').hide();
            $('#erb-step-' + name).show();

            // Update step indicator
            $('.erb-step').removeClass('is-active is-done');
            var stepNum = parseInt(name, 10);
            if (!isNaN(stepNum)) {
                for (var i = 1; i < stepNum; i++) $('[data-step="' + i + '"]').addClass('is-done');
                $('[data-step="' + stepNum + '"]').addClass('is-active');
            }
        },

        goToStep: function (n) {
            ERB.Booking.showPanel(String(n));
            if (n === 2) ERB.Booking.renderStep2Summary();
            if (n === 3) ERB.Booking.renderStep3();
            window.scrollTo({ top: $('#erb-booking-app').offset().top - 40, behavior: 'smooth' });
        },

        // ── Summary helpers ───────────────────────────────────────────────────

        renderSlotSummary: function () {
            var s    = ERB.Booking.state;
            var name = ERB.Booking.gameData ? ERB.Booking.gameData.name : 'Game';
            var date = ERB.Booking.formatDisplayDate(s.displayDate || s.slotStart || '');
            var time = s.displayTime || '';
            $('#erb-summary-game').text(name);
            $('#erb-summary-date').text(date);
            $('#erb-summary-time').text(time);
        },

        formatDisplayDate: function (dt) {
            if (!dt) return '';
            // Handle both 'Y-m-d H:i:s' and 'Y-m-d' formats from sessionStorage
            var d = new Date(dt.slice(0, 10).replace(/-/g, '/'));
            if (isNaN(d.getTime())) return dt;
            var months      = ['January','February','March','April','May','June',
                               'July','August','September','October','November','December'];
            var shortMonths = ['Jan','Feb','Mar','Apr','May','Jun',
                               'Jul','Aug','Sep','Oct','Nov','Dec'];
            var day    = d.getDate();
            var month  = d.getMonth();
            var year   = d.getFullYear();
            var pad    = function(n){ return ('0'+n).slice(-2); };
            // Build a token map and replace each token once using a regex scan
            var tokens = {
                'd': pad(day),
                'j': String(day),
                'n': String(month + 1),
                'm': pad(month + 1),
                'F': months[month],
                'M': shortMonths[month],
                'Y': String(year),
                'y': String(year).slice(-2)
            };
            var fmt = (eerbPublic && eerbPublic.dateFormat) ? eerbPublic.dateFormat : 'j F Y'; // default: 27 March 2026
            // Replace each PHP date token (preceded by optional backslash escape)
            return fmt.replace(/\\?([dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU])/g, function(match, token) {
                if (match.charAt(0) === '\\') return token; // escaped character
                return tokens.hasOwnProperty(token) ? tokens[token] : match;
            });
        },

        summaryHtml: function (withCustomer) {
            var s    = ERB.Booking.state;
            var name = ERB.Booking.gameData ? ERB.Booking.gameData.name : '—';
            var date = ERB.Booking.formatDisplayDate(s.displayDate || s.slotStart || '');
            var html = '<table style="width:100%;font-size:16px;border-collapse:collapse;">';
            html += '<tr><td style="color:#6b7280;padding:4px 0;width:130px;font-size:16px;">Game</td><td style="font-size:16px;"><strong>' + ERB.esc(name) + '</strong></td></tr>';
            html += '<tr><td style="color:#6b7280;padding:4px 0;font-size:16px;">Date &amp; Time</td><td style="font-size:16px;">' + ERB.esc(date) + ' at ' + ERB.esc(s.displayTime || '') + '</td></tr>';
            html += '<tr><td style="color:#6b7280;padding:4px 0;font-size:16px;">Players</td><td style="font-size:16px;">' + ERB.Booking.selectedPlayers + '</td></tr>';
            html += '<tr><td style="color:#6b7280;padding:4px 0;font-size:16px;">Price</td><td style="font-size:16px;">' + ERB.formatPrice(ERB.Booking.pricePence) + '</td></tr>';
            if (withCustomer && ERB.Booking.customerName) {
                html += '<tr><td style="color:#6b7280;padding:4px 0;font-size:16px;">Name</td><td style="font-size:16px;">' + ERB.esc(ERB.Booking.customerName) + '</td></tr>';
            }
            html += '</table>';
            return html;
        },

        renderStep2Summary: function () {
            $('#erb-step2-summary').html(ERB.Booking.summaryHtml(false));
        },

        renderStep3: function () {
            $('#erb-step3-summary').html(ERB.Booking.summaryHtml(true));
            ERB.Booking.updatePaymentPrice();
            ERB.Booking.initStripe();
        },

        // ── Step 1: Players ───────────────────────────────────────────────────

        step1Next: function () {
            if (!ERB.Booking.selectedPlayers) return;
            ERB.Booking.goToStep(2);
        },

        // ── Step 2: Details ───────────────────────────────────────────────────

        switchTab: function (tab) {
            ERB.Booking.tab = tab;
            $('.erb-checkout-tab').removeClass('is-active');
            $('[data-tab="' + tab + '"]').addClass('is-active');
            $('#erb-tab-guest, #erb-tab-login').hide();
            $('#erb-tab-' + tab).show();
        },

        doLogin: function () {
            var email    = $.trim($('#erb-login-email').val());
            var password = $('#erb-login-password').val();
            $('#erb-login-error').hide();

            if (!email || !password) {
                $('#erb-login-error').text('Please enter your email and password.').show();
                return;
            }

            var $btn = $('#erb-login-btn').prop('disabled', true).text('Logging in…');

            ERB.ajax('eerb_customer_login', { email: email, password: password },
            function (data) {
                ERB.Booking.customerId   = data.customer_id;
                ERB.Booking.customerName = data.first_name + ' ' + data.last_name;
                ERB.BookingState.set({ customerId: data.customer_id, customerName: ERB.Booking.customerName });
                $('#erb-login-success').text('Welcome back, ' + data.first_name + '!').show();
                $btn.prop('disabled', false).text('Log In');
            },
            function (msg) {
                $('#erb-login-error').text(msg || 'Login failed. Please check your details.').show();
                $btn.prop('disabled', false).text('Log In');
            });
        },

        step2Next: function () {
            var errors = [];

            if (ERB.Booking.tab === 'guest') {
                var fn  = $.trim($('#erb-first-name').val());
                var ln  = $.trim($('#erb-last-name').val());
                var em  = $.trim($('#erb-email').val());
                var mob = $.trim($('#erb-mobile').val());

                if (!fn)  errors.push('First name is required.');
                if (!ln)  errors.push('Last name is required.');
                if (!em || !ERB.validateEmail(em)) errors.push('A valid email address is required.');
                if (!mob || !ERB.validateMobile(mob)) errors.push('A valid mobile number is required.');

                var createAcct = $('#erb-create-account').is(':checked');
                if (createAcct && $('#erb-new-password').val().length < 8) {
                    errors.push('Password must be at least 8 characters.');
                }

                if (errors.length) { alert(errors.join('\n')); return; }

                ERB.BookingState.set({
                    firstName:     fn,
                    lastName:      ln,
                    email:         em,
                    mobile:        mob,
                    createAccount: createAcct,
                    newPassword:   createAcct ? $('#erb-new-password').val() : '',
                    isGuest:       true,
                });
                ERB.Booking.customerName = fn + ' ' + ln;

            } else {
                // Login tab — must have logged in
                if (!ERB.Booking.customerId) {
                    alert('Please log in before continuing.');
                    return;
                }
            }

            ERB.Booking.goToStep(3);
        },

        // ── Step 3: Payment ───────────────────────────────────────────────────

        applyPromo: function () {
            var code = $.trim($('#erb-promo-code').val()).toUpperCase();
            if (!code) return;

            $('#erb-promo-result').text('Checking…').css('color', '#6b7280');

            ERB.ajax('eerb_validate_promo', { code: code },
            function (data) {
                ERB.Booking.discountPct = data.discount_percent;
                ERB.Booking.promoId     = data.promo_id;
                ERB.BookingState.set({ promoCode: code, promoId: data.promo_id, discountPct: data.discount_percent });
                $('#erb-promo-result').text('✓ ' + data.discount_percent + '% discount applied!').css('color', '#166534');
                ERB.Booking.updatePaymentPrice();
            },
            function (msg) {
                ERB.Booking.discountPct = 0;
                ERB.Booking.promoId     = null;
                $('#erb-promo-result').text(msg || 'Invalid code.').css('color', '#ef4444');
                ERB.Booking.updatePaymentPrice();
            });
        },

        updatePaymentPrice: function () {
            var base     = ERB.Booking.pricePence;
            var discount = ERB.Booking.discountPct;
            var discountAmt = Math.round(base * discount / 100);
            ERB.Booking.totalPence = base - discountAmt;

            var sym = eerbPublic.currencySymbol || '£';
            $('#erb-payment-total').text(ERB.formatPrice(ERB.Booking.totalPence));
            $('#erb-payment-per').text(ERB.formatPrice(Math.round(ERB.Booking.totalPence / ERB.Booking.selectedPlayers)) + ' per person');

            if (discount > 0) {
                $('#erb-discount-badge').show();
                $('#erb-discount-label').text(discount + '% off — saving ' + ERB.formatPrice(discountAmt));
            } else {
                $('#erb-discount-badge').hide();
            }

            // Update pay button label
            $('#erb-pay-label').text('Pay ' + ERB.formatPrice(ERB.Booking.totalPence));
        },

        // ── Stripe ────────────────────────────────────────────────────────────

        initStripe: function () {
            if (ERB.Booking.stripe) return; // already initialised

            var pk = eerbPublic.stripePublicKey || '';
            if (!pk) {
                $('#erb-stripe-card-element').html('<p style="color:#ef4444;">Payment gateway not configured. Please contact us to complete your booking.</p>');
                $('#erb-pay-btn').prop('disabled', true);
                return;
            }

            ERB.Booking.stripe = Stripe(pk);
            var elements = ERB.Booking.stripe.elements();
            ERB.Booking.cardElement = elements.create('card', {
                style: {
                    base: { fontSize: '16px', color: '#111827', '::placeholder': { color: '#9ca3af' } },
                    invalid: { color: '#ef4444' },
                },
                hidePostalCode: true,
            });
            ERB.Booking.cardElement.mount('#erb-stripe-card-element');
            ERB.Booking.cardElement.on('change', function (e) {
                if (e.error) {
                    $('#erb-stripe-card-errors').text(e.error.message).show();
                } else {
                    $('#erb-stripe-card-errors').hide();
                }
            });
        },

        submitPayment: function () {
            if (!ERB.Booking.stripe || !ERB.Booking.cardElement) {
                alert('Payment not ready. Please wait a moment and try again.');
                return;
            }

            var $btn = $('#erb-pay-btn').prop('disabled', true);
            $('#erb-pay-label').hide();
            $('#erb-pay-spinner').show();
            $('#erb-stripe-card-errors').hide();

            // 1. Create booking record + payment intent on server
            var s = ERB.BookingState.get();

            ERB.ajax('eerb_create_booking', {
                game_id:      s.gameId,
                slot_start:   s.slotStart,
                slot_end:     s.slotEnd,
                session_key:  s.sessionKey,
                player_count: ERB.Booking.selectedPlayers,
                // Guest details
                first_name:   s.firstName   || '',
                last_name:    s.lastName    || '',
                email:        s.email       || '',
                mobile:       s.mobile      || '',
                create_account: s.createAccount ? 1 : 0,
                new_password:   s.newPassword || '',
                is_guest:       s.isGuest ? 1 : 0,
                // Existing customer
                customer_id:  s.customerId  || '',
                // Promo
                promo_id:     s.promoId     || '',
            },
            function (data) {
                // 2. Confirm card payment with Stripe.js
                ERB.Booking.stripe.confirmCardPayment(data.client_secret, {
                    payment_method: {
                        card: ERB.Booking.cardElement,
                        billing_details: {
                            name: (s.firstName || '') + ' ' + (s.lastName || ''),
                            email: s.email || '',
                        },
                    },
                }).then(function (result) {
                    if (result.error) {
                        $('#erb-stripe-card-errors').text(result.error.message).show();
                        ERB.Booking.resetPayBtn();
                    } else if (result.paymentIntent.status === 'succeeded') {
                        // 3. Notify server that payment succeeded, then show confirmation.
                        // booking_ref comes from the create_booking response (data) since
                        // the confirm_payment endpoint is a security no-op in v1.3.1+.
                        ERB.ajax('eerb_confirm_payment', {
                            booking_id:        data.booking_id,
                            payment_intent_id: result.paymentIntent.id,
                        },
                        function () {
                            ERB.Booking.onSuccess({ booking_ref: data.booking_ref });
                        },
                        function () {
                            ERB.Booking.onSuccess({ booking_ref: data.booking_ref });
                        });
                    }
                });
            },
            function (msg) {
                $('#erb-stripe-card-errors').text(msg || 'Could not create booking. Please try again.').show();
                ERB.Booking.resetPayBtn();
            });
        },

        resetPayBtn: function () {
            $('#erb-pay-btn').prop('disabled', false);
            $('#erb-pay-label').show();
            $('#erb-pay-spinner').hide();
        },

        // ── Success ───────────────────────────────────────────────────────────

        onSuccess: function (data) {
            if (ERB.Booking.timer) ERB.Booking.timer.stop();
            $('#erb-timer-bar').hide();

            // Build summary BEFORE clearing state so date/time values are still available
            var summaryContent = ERB.Booking.summaryHtml(true);
            var ref = data.booking_ref || '';

            ERB.BookingState.clear();

            $('#erb-success-ref').text('Your booking reference is: ' + ref);
            $('#erb-success-summary').html(summaryContent);

            ERB.Booking.showPanel('success');
            window.scrollTo({ top: $('#erb-booking-app').offset().top - 40, behavior: 'smooth' });
        },
    };

    // ── Player button click ───────────────────────────────────────────────────

    $(document).on('click', '.erb-player-btn', function () {
        var players  = parseInt($(this).data('players'), 10);
        var gameData = ERB.Booking.gameData;
        var prices   = (gameData && gameData.prices) ? gameData.prices : {};
        // JSON object keys are always strings; try both string and integer
        var price    = prices[String(players)] || prices[players] || 0;

        ERB.Booking.selectedPlayers = players;
        ERB.Booking.pricePence      = price;
        ERB.BookingState.set({ playerCount: players, pricePence: price });

        $('.erb-player-btn').removeClass('is-selected');
        $(this).addClass('is-selected');

        if (price > 0) {
            $('#erb-price-total').text(ERB.formatPrice(price));
            $('#erb-price-per').text(ERB.formatPrice(Math.round(price / players)) + ' per person');
            $('#erb-price-players-label').text(players + ' player' + (players !== 1 ? 's' : ''));
            $('#erb-price-box').show();
            $('#erb-step1-next').show();
        } else {
            // Price not configured — show warning instead of silently doing nothing
            $('#erb-price-total').text('Price not set');
            $('#erb-price-per').text('Please configure pricing for this game in the admin panel.');
            $('#erb-price-box').show().css('background','#fef2f2').css('border-color','#fecaca');
            $('#erb-step1-next').hide();
        }
    });

    // ── Init ─────────────────────────────────────────────────────────────────

    $(function () {
        if ($('#erb-booking-app').length) {
            ERB.Booking.init();
        }
    });

}(jQuery));
