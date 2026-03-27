/**
 * Gigbuilder Availability Widget
 */
(function() {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function() {
        var widget = document.getElementById( 'gigbuilder-availability' );
        if ( ! widget ) return;

        // Check if already booked this session
        var booked = sessionStorage.getItem( 'gigbuilder_booked' );
        if ( booked ) {
            try {
                var info = JSON.parse( booked );
                widget.innerHTML = '<div class="gigbuilder-message gigbuilder-message--success">' + info.message + '</div>'
                    + '<p class="gigbuilder-booked-date"><strong>' + info.date + '</strong></p>';
            } catch(e) {
                sessionStorage.removeItem( 'gigbuilder_booked' );
            }
            if ( booked && widget.querySelector( '.gigbuilder-booked-date' ) ) return;
        }

        // No active booking — show the date picker
        widget.querySelector( '.gigbuilder-date-picker' ).style.display = '';

        var config        = window.gigbuilderAvailability || {};
        var checkBtn      = widget.querySelector( '.gigbuilder-check-btn' );
        var messageEl     = widget.querySelector( '.gigbuilder-message' );
        var formContainer = widget.querySelector( '.gigbuilder-form-container' );
        var loadingEl     = widget.querySelector( '.gigbuilder-loading' );
        var calendarWrap  = widget.querySelector( '.gigbuilder-date-calendar' );
        var dropdownWrap  = widget.querySelector( '.gigbuilder-date-dropdowns' );
        var datePicker    = widget.querySelector( '.gigbuilder-date-picker' );
        var selectedDateEl = widget.querySelector( '.gigbuilder-selected-date' );
        var validationEl  = widget.querySelector( '.gigbuilder-validation-errors' );
        var modeRadios    = widget.querySelectorAll( 'input[name="gb-date-mode"]' );

        // Populate dropdowns
        var monthSelect = document.getElementById( 'gb-date-month' );
        var daySelect   = document.getElementById( 'gb-date-day' );
        var yearSelect  = document.getElementById( 'gb-date-year' );

        var monthNames = ['January','February','March','April','May','June',
                          'July','August','September','October','November','December'];
        for ( var m = 0; m < monthNames.length; m++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( m + 1 );
            opt.textContent = monthNames[m];
            monthSelect.appendChild( opt );
        }

        for ( var d = 1; d <= 31; d++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( d );
            opt.textContent = String( d );
            daySelect.appendChild( opt );
        }

        var currentYear = new Date().getFullYear();
        for ( var y = currentYear; y <= currentYear + 3; y++ ) {
            var opt = document.createElement( 'option' );
            opt.value = String( y );
            opt.textContent = String( y );
            yearSelect.appendChild( opt );
        }

        // Toggle date mode (only if calendar variant)
        if ( modeRadios.length > 0 ) {
            for ( var i = 0; i < modeRadios.length; i++ ) {
                modeRadios[i].addEventListener( 'change', function() {
                    if ( this.value === 'calendar' ) {
                        calendarWrap.style.display = '';
                        dropdownWrap.style.display = 'none';
                    } else {
                        calendarWrap.style.display = 'none';
                        dropdownWrap.style.display = '';
                    }
                });
            }
        }

        /**
         * Get the selected date in MM/DD/YYYY format.
         */
        function getSelectedDate() {
            var modeEl = widget.querySelector( 'input[name="gb-date-mode"]:checked' );
            var mode = modeEl ? modeEl.value : 'dropdowns';

            if ( mode === 'calendar' ) {
                var val = document.getElementById( 'gb-date-calendar' ).value;
                if ( ! val ) return '';
                var parts = val.split( '-' );
                return parts[1] + '/' + parts[2] + '/' + parts[0];
            } else {
                var mm = monthSelect.value;
                var dd = daySelect.value;
                var yy = yearSelect.value;
                if ( ! mm || ! dd || ! yy ) return '';
                return mm + '/' + dd + '/' + yy;
            }
        }

        /**
         * Format MM/DD/YYYY to a readable date string.
         */
        function formatDate( dateStr ) {
            var parts = dateStr.split( '/' );
            var m = parseInt( parts[0], 10 ) - 1;
            var d = parseInt( parts[1], 10 );
            var y = parts[2];
            return monthNames[m] + ' ' + d + ', ' + y;
        }

        /**
         * Show the date picker, hide the form area.
         */
        function showDatePicker() {
            datePicker.style.display = '';
            selectedDateEl.style.display = 'none';
            GigbuilderTools.hideMessage( messageEl );
            hideValidation();
            formContainer.innerHTML = '';
        }

        function hideValidation() {
            validationEl.style.display = 'none';
            validationEl.innerHTML = '';
        }

        // Check date button
        checkBtn.addEventListener( 'click', function() {
            var date = getSelectedDate();
            if ( ! date ) {
                GigbuilderTools.showMessage( messageEl, 'error', 'Please select a date.' );
                formContainer.innerHTML = '';
                return;
            }

            GigbuilderTools.hideMessage( messageEl );
            hideValidation();
            formContainer.innerHTML = '';
            loadingEl.style.display = 'block';

            var formData = new FormData();
            formData.append( 'action', 'gigbuilder_check_date' );
            formData.append( 'nonce', config.nonce );
            formData.append( 'date', date );

            fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                .then( function( res ) { return res.json(); } )
                .then( function( response ) {
                    loadingEl.style.display = 'none';

                    if ( ! response.success ) {
                        GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                        return;
                    }

                    var data = response.data;

                    if ( data.status === 'available' && data.formHtml ) {
                        GigbuilderTools.hideMessage( messageEl );
                        // Hide date picker, show selected date
                        datePicker.style.display = 'none';
                        selectedDateEl.innerHTML = '<strong>' + formatDate( date ) + '</strong> &nbsp; <a href="#" class="gigbuilder-change-date">Change Date</a>';
                        selectedDateEl.style.display = '';

                        // Attach change date link
                        selectedDateEl.querySelector( '.gigbuilder-change-date' ).addEventListener( 'click', function( e ) {
                            e.preventDefault();
                            showDatePicker();
                        });

                        formContainer.innerHTML = data.formHtml;
                        attachFormHandler( formContainer.querySelector( '.gigbuilder-form' ), date );
                    } else {
                        GigbuilderTools.showMessage( messageEl, data.status, data.message );
                    }
                })
                .catch( function() {
                    loadingEl.style.display = 'none';
                    GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
                });
        });

        /**
         * Validate required fields. Returns array of missing field labels.
         */
        function validateForm( formEl ) {
            var missing = [];
            var fields = formEl.querySelectorAll( '[required]' );

            for ( var i = 0; i < fields.length; i++ ) {
                var el = fields[i];
                if ( el.type === 'hidden' ) continue; // skip hidden time/duration fields

                var val = el.value ? el.value.trim() : '';
                if ( ! val ) {
                    // Find the label text
                    var labelText = '';
                    var fieldWrap = el.closest( '.gigbuilder-field' );
                    if ( fieldWrap ) {
                        var labelEl = fieldWrap.querySelector( 'label' );
                        if ( labelEl ) {
                            labelText = labelEl.textContent.replace( /\s*\*\s*$/, '' ).trim();
                        }
                    }
                    missing.push( labelText || el.name );
                }
            }

            return missing;
        }

        /**
         * Attach submit handler to the CRM-rendered booking form.
         */
        function attachFormHandler( formEl, date ) {
            if ( ! formEl ) return;

            formEl.addEventListener( 'submit', function( e ) {
                e.preventDefault();
                hideValidation();

                // Validate required fields
                var missing = validateForm( formEl );
                if ( missing.length > 0 ) {
                    var html = '<strong>Please complete the following:</strong><ul>';
                    for ( var i = 0; i < missing.length; i++ ) {
                        html += '<li>' + missing[i] + '</li>';
                    }
                    html += '</ul>';
                    validationEl.innerHTML = html;
                    validationEl.style.display = '';
                    validationEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }

                var submitBtn = formEl.querySelector( '.gigbuilder-submit' );
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';

                var answers = GigbuilderTools.collectFormAnswers( formEl );
                loadingEl.style.display = 'block';

                var formData = new FormData();
                formData.append( 'action', 'gigbuilder_submit_booking' );
                formData.append( 'nonce', config.nonce );
                formData.append( 'date', date );
                formData.append( 'answers', JSON.stringify( answers ) );

                fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                    .then( function( res ) { return res.json(); } )
                    .then( function( response ) {
                        loadingEl.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit';

                        if ( ! response.success ) {
                            GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                            return;
                        }

                        var data = response.data;

                        if ( data.status === 'success' ) {
                            // Lock the widget — hide everything, show success
                            var dateDisplay = formatDate( date );
                            sessionStorage.setItem( 'gigbuilder_booked', JSON.stringify({
                                date: dateDisplay,
                                message: data.message
                            }) );
                            widget.innerHTML = '<div class="gigbuilder-message gigbuilder-message--success">' + data.message + '</div>'
                                + '<p class="gigbuilder-booked-date"><strong>' + dateDisplay + '</strong></p>';
                        } else {
                            GigbuilderTools.showMessage( messageEl, data.status, data.message );
                        }
                    })
                    .catch( function() {
                        loadingEl.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit';
                        GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
                    });
            });
        }
    });
})();
