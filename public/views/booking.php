<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="erb-wrap">
<div class="erb-booking-wrap" id="erb-booking-app">

    <!-- Step indicator -->
    <div class="erb-steps" id="erb-steps">
        <div class="erb-step is-active" data-step="1"><span class="erb-step__num">1</span><span class="erb-step__label"><?php esc_html_e( 'Players', 'ettrick-escape-room-booking' ); ?></span></div>
        <div class="erb-step" data-step="2"><span class="erb-step__num">2</span><span class="erb-step__label"><?php esc_html_e( 'Details', 'ettrick-escape-room-booking' ); ?></span></div>
        <div class="erb-step" data-step="3"><span class="erb-step__num">3</span><span class="erb-step__label"><?php esc_html_e( 'Payment', 'ettrick-escape-room-booking' ); ?></span></div>
    </div>

    <!-- Timer -->
    <div class="erb-timer-bar" id="erb-timer-bar" style="display:none;">
        <span><?php esc_html_e( 'Slot reserved for:', 'ettrick-escape-room-booking' ); ?></span>
        <span class="erb-timer" id="erb-timer-display">15:00</span>
        <span><?php esc_html_e( 'Complete your booking before time runs out.', 'ettrick-escape-room-booking' ); ?></span>
    </div>

    <!-- Loading -->
    <div class="erb-booking-step" id="erb-step-loading">
        <div style="text-align:center;padding:2.5rem;">
            <div class="erb-spinner-dark" style="width:28px;height:28px;margin:0 auto 1rem;"></div>
            <p style="color:#6b7280;"><?php esc_html_e( 'Loading…', 'ettrick-escape-room-booking' ); ?></p>
        </div>
    </div>

    <!-- No state -->
    <div class="erb-booking-step" id="erb-step-no-state" style="display:none;">
        <div class="erb-result">
            <div class="erb-result__icon">⏰</div>
            <h2><?php esc_html_e( 'No slot selected', 'ettrick-escape-room-booking' ); ?></h2>
            <p><?php esc_html_e( 'Your session may have expired or you arrived here directly. Please choose a time slot from the calendar.', 'ettrick-escape-room-booking' ); ?></p>
            <a href="javascript:history.back();" class="erb-btn erb-btn--primary" style="margin-top:1rem;display:inline-flex;width:auto;">← <?php esc_html_e( 'Back to Calendar', 'ettrick-escape-room-booking' ); ?></a>
        </div>
    </div>

    <!-- Step 1: Players -->
    <div class="erb-booking-step" id="erb-step-1" style="display:none;">
        <h2><?php esc_html_e( 'Select Number of Players', 'ettrick-escape-room-booking' ); ?></h2>
        <div class="erb-slot-summary" id="erb-slot-summary">
            <strong id="erb-summary-game">—</strong> &nbsp;·&nbsp;
            <span id="erb-summary-date">—</span> &nbsp;·&nbsp;
            <span id="erb-summary-time">—</span>
        </div>
        <p class="erb-players-label"><?php esc_html_e( 'How many players will be attending?', 'ettrick-escape-room-booking' ); ?></p>
        <div class="erb-player-grid" id="erb-player-grid">
            <!-- Buttons rendered by JS using gameData.max_players -->
        </div>
        <div class="erb-price-box" id="erb-price-box" style="display:none;">
            <div>
                <div class="erb-price-box__total" id="erb-price-total">—</div>
                <div class="erb-price-box__per-person" id="erb-price-per">—</div>
            </div>
            <div id="erb-price-players-label" style="font-size:14px;color:#6b7280;">—</div>
        </div>
        <button class="erb-btn erb-btn--primary" id="erb-step1-next" style="display:none;" onclick="ERB.Booking.step1Next()">
            <?php esc_html_e( 'Continue', 'ettrick-escape-room-booking' ); ?> →
        </button>
    </div>

    <!-- Step 2: Customer details -->
    <div class="erb-booking-step" id="erb-step-2" style="display:none;">
        <h2><?php esc_html_e( 'Your Details', 'ettrick-escape-room-booking' ); ?></h2>
        <div class="erb-summary" id="erb-step2-summary"></div>

        <div class="erb-checkout-tabs">
            <div class="erb-checkout-tab is-active" data-tab="guest" onclick="ERB.Booking.switchTab('guest')"><?php esc_html_e( 'Guest Checkout', 'ettrick-escape-room-booking' ); ?></div>
            <div class="erb-checkout-tab" data-tab="login" onclick="ERB.Booking.switchTab('login')"><?php esc_html_e( 'Existing Customer', 'ettrick-escape-room-booking' ); ?></div>
        </div>

        <!-- Guest -->
        <div id="erb-tab-guest">
            <div style="display:flex;gap:.75rem;">
                <div class="erb-field" style="flex:1;"><label><?php esc_html_e( 'First Name', 'ettrick-escape-room-booking' ); ?> *</label><input type="text" id="erb-first-name" autocomplete="given-name"></div>
                <div class="erb-field" style="flex:1;"><label><?php esc_html_e( 'Last Name', 'ettrick-escape-room-booking' ); ?> *</label><input type="text" id="erb-last-name" autocomplete="family-name"></div>
            </div>
            <div class="erb-field"><label><?php esc_html_e( 'Email Address', 'ettrick-escape-room-booking' ); ?> *</label><input type="email" id="erb-email" autocomplete="email"></div>
            <div class="erb-field"><label><?php esc_html_e( 'Mobile Number', 'ettrick-escape-room-booking' ); ?> *</label><input type="tel" id="erb-mobile" autocomplete="tel"></div>
            <div class="erb-field">
                <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;cursor:pointer;">
                    <input type="checkbox" id="erb-create-account"> <?php esc_html_e( 'Create an account for faster future bookings', 'ettrick-escape-room-booking' ); ?>
                </label>
            </div>
            <div id="erb-create-password-wrap" style="display:none;">
                <div class="erb-field">
                    <label><?php esc_html_e( 'Choose a Password', 'ettrick-escape-room-booking' ); ?></label>
                    <div class="erb-password-wrap">
                        <input type="password" id="erb-new-password" autocomplete="new-password" minlength="8">
                        <button type="button" class="erb-pwd-toggle" aria-label="<?php esc_attr_e( 'Show password', 'ettrick-escape-room-booking' ); ?>" onclick="ERB.Booking.togglePassword('erb-new-password', this)">
                            <svg class="erb-eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="erb-eye-off-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <small style="color:#9ca3af;"><?php esc_html_e( 'Minimum 8 characters', 'ettrick-escape-room-booking' ); ?></small>
                </div>
                <div class="erb-field">
                    <label><?php esc_html_e( 'Confirm Password', 'ettrick-escape-room-booking' ); ?></label>
                    <div class="erb-password-wrap">
                        <input type="password" id="erb-confirm-password" autocomplete="new-password" minlength="8">
                        <button type="button" class="erb-pwd-toggle" aria-label="<?php esc_attr_e( 'Show password', 'ettrick-escape-room-booking' ); ?>" onclick="ERB.Booking.togglePassword('erb-confirm-password', this)">
                            <svg class="erb-eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="erb-eye-off-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login -->
        <div id="erb-tab-login" style="display:none;">
            <div class="erb-field"><label><?php esc_html_e( 'Email Address', 'ettrick-escape-room-booking' ); ?> *</label><input type="email" id="erb-login-email" autocomplete="email"></div>
            <div class="erb-field"><label><?php esc_html_e( 'Password', 'ettrick-escape-room-booking' ); ?> *</label><input type="password" id="erb-login-password" autocomplete="current-password"></div>
            <div id="erb-login-error" style="color:#ef4444;font-size:14px;display:none;margin-bottom:.75rem;"></div>
            <button class="erb-btn erb-btn--outline" onclick="ERB.Booking.doLogin()" style="margin-bottom:1rem;width:auto;display:inline-flex;"><?php esc_html_e( 'Log In', 'ettrick-escape-room-booking' ); ?></button>
            <div id="erb-login-success" style="display:none;color:#166534;font-size:15px;margin-bottom:.75rem;"></div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1rem;">
            <button class="erb-btn erb-btn--outline" onclick="ERB.Booking.goToStep(1)">← <?php esc_html_e( 'Back', 'ettrick-escape-room-booking' ); ?></button>
            <button class="erb-btn erb-btn--primary" id="erb-step2-next" onclick="ERB.Booking.step2Next()"><?php esc_html_e( 'Continue to Payment', 'ettrick-escape-room-booking' ); ?> →</button>
        </div>
    </div>

    <!-- Step 3: Payment -->
    <div class="erb-booking-step" id="erb-step-3" style="display:none;">
        <h2><?php esc_html_e( 'Payment', 'ettrick-escape-room-booking' ); ?></h2>
        <div class="erb-summary" id="erb-step3-summary"></div>

        <?php if ( ! defined( 'EERB_LITE' ) ) : ?>
        <div class="erb-promo-row">
            <input type="text" id="erb-promo-code" placeholder="<?php esc_attr_e( 'Promo code', 'ettrick-escape-room-booking' ); ?>" style="text-transform:uppercase;">
            <button class="erb-btn erb-btn--outline erb-btn--auto" onclick="ERB.Booking.applyPromo()"><?php esc_html_e( 'Apply', 'ettrick-escape-room-booking' ); ?></button>
        </div>
        <div id="erb-promo-result" style="margin-bottom:.75rem;font-size:14px;"></div>
        <?php endif; ?>

        <div class="erb-price-box" id="erb-payment-price-box">
            <div>
                <div class="erb-price-box__total" id="erb-payment-total">—</div>
                <div class="erb-price-box__per-person" id="erb-payment-per">—</div>
            </div>
            <div id="erb-discount-badge" style="display:none;"><span class="erb-badge erb-badge--confirmed" id="erb-discount-label"></span></div>
        </div>

        <div class="erb-field">
            <label><?php esc_html_e( 'Card Details', 'ettrick-escape-room-booking' ); ?></label>
            <div id="erb-stripe-card-element" class="erb-stripe-element"></div>
            <div id="erb-stripe-card-errors" style="color:#ef4444;font-size:14px;margin-top:.35rem;display:none;"></div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
            <button class="erb-btn erb-btn--outline" onclick="ERB.Booking.goToStep(2)">← <?php esc_html_e( 'Back', 'ettrick-escape-room-booking' ); ?></button>
            <button class="erb-btn erb-btn--success" id="erb-pay-btn" onclick="ERB.Booking.submitPayment()">
                <span id="erb-pay-label"><?php esc_html_e( 'Pay Now', 'ettrick-escape-room-booking' ); ?></span>
                <span class="erb-spinner" id="erb-pay-spinner" style="display:none;"></span>
            </button>
        </div>
        <p style="font-size:14px;color:#6b7280;margin-top:.75rem;text-align:center;"><?php esc_html_e( 'Payments processed securely by Stripe. Your card details never touch our server.', 'ettrick-escape-room-booking' ); ?></p>
    </div>

    <!-- Success -->
    <div class="erb-booking-step" id="erb-step-success" style="display:none;">
        <div class="erb-result">
            <div class="erb-result__icon">🎉</div>
            <h2><?php esc_html_e( 'Booking Confirmed!', 'ettrick-escape-room-booking' ); ?></h2>
            <p id="erb-success-ref" style="font-size:16px;color:#374151;font-weight:600;"></p>
            <div class="erb-summary" id="erb-success-summary" style="margin:1.25rem 0;text-align:left;"></div>
            <p style="font-size:16px;color:#6b7280;margin-bottom:1.5rem;"><?php esc_html_e( 'A confirmation email is on its way. Use the link in that email to manage or cancel your booking.', 'ettrick-escape-room-booking' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="erb-btn erb-btn--primary" style="display:inline-flex;width:auto;">
                <?php esc_html_e( '← Back to Home', 'ettrick-escape-room-booking' ); ?>
            </a>
        </div>
    </div>

</div>
</div>

<?php
// Game prices are passed to JS via eerbPublic.gamesData in class-erb-public.php enqueue_assets()
?>
