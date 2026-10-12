/* global ERB, eerbAdmin, jQuery */
(function ($) {
    'use strict';

    window.EERBGames = {

        // ── Room Modal ────────────────────────────────────────────────────────

        openRoomModal: function (id, data) {
            $('#erb-room-id').val(id || '');
            $('#erb-room-modal-title').text(id ? 'Edit Room' : 'Add Room');
            $('#erb-room-name').val(data ? data.name : '');
            $('#erb-room-desc').val(data ? (data.description || '') : '');
            ERB.openModal('erb-room-modal');
        },

        saveRoom: function () {
            var name = $.trim($('#erb-room-name').val());
            if (!name) { alert('Room name is required.'); return; }

            ERB.ajax('eerb_save_room', {
                id:          $('#erb-room-id').val(),
                name:        name,
                description: $('#erb-room-desc').val(),
            }, function () {
                ERB.closeModal('erb-room-modal');
                ERB.notice('Room saved.');
                setTimeout(function () { location.reload(); }, 800);
            });
        },

        // ── Game Modal ────────────────────────────────────────────────────────

        openGameModal: function (id) {
            $('#erb-game-id').val(id || '');
            $('#erb-game-modal-title').text(id ? 'Edit Game' : 'Add Game');

            // Reset form
            $('#erb-game-name, #erb-game-slug, #erb-game-description, #erb-game-image').val('');
            $('#erb-game-room').val('');
            $('#erb-game-status').val('active');
            $('#erb-game-duration').val(60);
            $('#erb-game-setup').val(30);
            $('#erb-game-notice').val(2);
            $('#erb-game-horizon').val('');
            $('#erb-game-min-players').val(2);
            $('#erb-game-max-players').val(8);

            if (id) {
                // Load existing game data
                ERB.ajax('eerb_get_game', { id: id }, function (game) {
                    $('#erb-game-name').val(game.name);
                    $('#erb-game-slug').val(game.slug);
                    $('#erb-game-room').val(game.room_id);
                    $('#erb-game-status').val(game.status);
                    $('#erb-game-duration').val(game.duration_minutes);
                    $('#erb-game-setup').val(game.setup_minutes);
                    $('#erb-game-notice').val(game.min_notice_hours);
                    $('#erb-game-horizon').val(game.booking_horizon_date || '');
                    $('#erb-game-description').val(game.description || '');
                    $('#erb-game-image').val(game.image_url || '');
                    $('#erb-game-min-players').val(game.min_players || 2);
                    $('#erb-game-max-players').val(game.max_players || 8);
                });
            }

            ERB.openModal('erb-game-modal');
        },

        saveGame: function () {
            var name = $.trim($('#erb-game-name').val());
            var room = $('#erb-game-room').val();
            if (!name) { alert('Game name is required.'); return; }
            if (!room) { alert('Please select a physical room.'); return; }

            ERB.ajax('eerb_save_game', {
                id:                   $('#erb-game-id').val(),
                name:                 name,
                slug:                 $('#erb-game-slug').val() || name,
                room_id:              room,
                status:               $('#erb-game-status').val(),
                duration_minutes:     $('#erb-game-duration').val(),
                setup_minutes:        $('#erb-game-setup').val(),
                min_notice_hours:     $('#erb-game-notice').val(),
                booking_horizon_date: $('#erb-game-horizon').val(),
                description:          $('#erb-game-description').val(),
                image_url:            $('#erb-game-image').val(),
                min_players:          $('#erb-game-min-players').val(),
                max_players:          $('#erb-game-max-players').val(),
            }, function () {
                ERB.closeModal('erb-game-modal');
                ERB.notice('Game saved.');
                setTimeout(function () { location.reload(); }, 800);
            });
        },

        // ── Hours Modal ───────────────────────────────────────────────────────

        openHoursModal: function (gameId, gameName) {
            $('#erb-hours-game-id').val(gameId);
            $('#erb-hours-modal-title').text('Operating Hours — ' + gameName);

            // Reset to defaults
            $('.erb-hours-open').each(function () { $(this).val('10:00'); });
            $('.erb-hours-close').each(function () { $(this).val('22:00'); });
            $('.erb-hours-closed').prop('checked', false);

            // Load saved hours
            ERB.ajax('eerb_get_game', { id: gameId }, function (game) {
                if (!game.hours || !game.hours.length) return;
                game.hours.forEach(function (h) {
                    var day = h.day_of_week;
                    $('.erb-hours-open[data-day="' + day + '"]').val(h.open_time ? h.open_time.slice(0,5) : '10:00');
                    $('.erb-hours-close[data-day="' + day + '"]').val(h.close_time ? h.close_time.slice(0,5) : '22:00');
                    $('.erb-hours-closed[data-day="' + day + '"]').prop('checked', h.is_closed == 1);
                });
                // Grey out closed rows
                $('.erb-hours-closed').trigger('change');
            });

            ERB.openModal('erb-hours-modal');
        },

        saveHours: function () {
            var gameId = $('#erb-hours-game-id').val();
            var hours  = {};

            $('.erb-hours-open').each(function () {
                var day       = $(this).data('day');
                var isClosed  = $('.erb-hours-closed[data-day="' + day + '"]').is(':checked');
                hours[day] = {
                    open_time:  isClosed ? '' : $(this).val(),
                    close_time: isClosed ? '' : $('.erb-hours-close[data-day="' + day + '"]').val(),
                    is_closed:  isClosed ? 1 : 0,
                };
            });

            ERB.ajax('eerb_save_hours', { game_id: gameId, hours: hours }, function () {
                ERB.closeModal('erb-hours-modal');
                ERB.notice('Hours saved.');
            });
        },

        // ── Pricing Modal ─────────────────────────────────────────────────────

        openPricingModal: function (gameId, gameName) {
            $('#erb-pricing-game-id').val(gameId);
            $('#erb-pricing-modal-title').text('Pricing — ' + gameName);

            var sym      = (window.eerbAdmin && eerbAdmin.currencySymbol) ? eerbAdmin.currencySymbol : '£';
            var defaults = {2:65,3:87,4:105,5:120,6:132,7:140,8:150};

            ERB.ajax('eerb_get_game', { id: gameId }, function (game) {
                var minP = parseInt(game.min_players, 10) || 2;
                var maxP = parseInt(game.max_players, 10) || 8;

                // Build rows dynamically for this game's player range
                var $tbody = $('#erb-pricing-tbody').empty();
                for (var p = minP; p <= maxP; p++) {
                    var defaultVal = defaults[p] ? defaults[p].toFixed(2) : '';
                    var perPerson  = (defaults[p] && p > 0) ? (defaults[p] / p).toFixed(2) : '0.00';
                    $tbody.append(
                        '<tr class="erb-pricing-row" data-players="' + p + '">' +
                        '<td style="text-align:center;font-weight:700;font-size:1rem;">' + p + '</td>' +
                        '<td><div style="display:flex;align-items:center;gap:.3rem;">' +
                        '<span>' + sym + '</span>' +
                        '<input type="number" class="erb-price-input" data-players="' + p + '"' +
                        ' value="' + defaultVal + '" min="0" step="0.01"' +
                        ' style="width:80px;padding:.3rem .5rem;border:1px solid #e5e7eb;border-radius:4px;"' +
                        ' oninput="EERBGames.updatePerPerson(this)">' +
                        '</div></td>' +
                        '<td class="erb-per-person-' + p + '" style="color:#6b7280;font-size:.85rem;">' +
                        sym + perPerson + '</td>' +
                        '</tr>'
                    );
                }

                // Overlay saved prices
                if (game.prices && game.prices.length) {
                    game.prices.forEach(function (pr) {
                        var $input = $('.erb-price-input[data-players="' + pr.player_count + '"]');
                        if ($input.length) {
                            $input.val((pr.price_pence / 100).toFixed(2));
                            EERBGames.updatePerPerson($input[0]);
                        }
                    });
                }
            });

            ERB.openModal('erb-pricing-modal');
        },

        savePricing: function () {
            var gameId = $('#erb-pricing-game-id').val();
            var prices = {};
            // Only save rows currently in the table (matches the game's min/max range)
            $('#erb-pricing-tbody .erb-price-input').each(function () {
                prices[$(this).data('players')] = $(this).val();
            });
            ERB.ajax('eerb_save_pricing', { game_id: gameId, prices: prices }, function () {
                ERB.closeModal('erb-pricing-modal');
                ERB.notice('Pricing saved.');
            }, function (err) {
                ERB.notice('Failed to save pricing: ' + err, 'error');
            });
        },

        updatePerPerson: function (input) {
            var $input   = $(input);
            var players  = parseInt($input.data('players'), 10);
            var total    = parseFloat($input.val()) || 0;
            var perPerson = players > 0 ? (total / players).toFixed(2) : '0.00';
            var symbol   = (window.eerbAdmin && eerbAdmin.currencySymbol) ? eerbAdmin.currencySymbol : '£';
            $('.erb-per-person-' + players).text(symbol + perPerson);
        },

        // ── Slug auto-generation ──────────────────────────────────────────────

        init: function () {
            // Auto-generate slug from name when adding a new game
            $('#erb-game-name').on('input', function () {
                if ($('#erb-game-id').val()) return; // don't overwrite on edit
                var slug = $(this).val()
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                $('#erb-game-slug').val(slug);
            });

            // Toggle disabled state on closed checkbox
            $(document).on('change', '.erb-hours-closed', function () {
                var day     = $(this).data('day');
                var closed  = $(this).is(':checked');
                $('.erb-hours-open[data-day="' + day + '"]').prop('disabled', closed);
                $('.erb-hours-close[data-day="' + day + '"]').prop('disabled', closed);
            });
        },
    };

    $(function () { EERBGames.init(); });

}(jQuery));
