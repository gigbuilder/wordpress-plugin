/**
 * Gigbuilder Availability Widget
 */
(function() {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function() {
        var widget = document.getElementById( 'gigbuilder-availability' );
        if ( ! widget ) return;

        // Detect style
        var isStepped = widget.classList.contains( 'gigbuilder-style-stepped' );
        var isCard    = widget.classList.contains( 'gigbuilder-style-card' );
        var isMinimal = widget.classList.contains( 'gigbuilder-style-minimal' );

        // Check if already booked this session
        var booked = sessionStorage.getItem( 'gigbuilder_booked' );
        if ( booked ) {
            try {
                var info = JSON.parse( booked );
                showSuccess( info.message, info.date );
            } catch(e) {
                sessionStorage.removeItem( 'gigbuilder_booked' );
            }
            if ( booked && widget.querySelector( '.gigbuilder-success' ) ) return;
        }

        // No active booking — show the date picker
        var datePickerEl = widget.querySelector( '.gigbuilder-date-picker' );
        if ( datePickerEl ) datePickerEl.style.display = '';

        var config        = window.gigbuilderAvailability || {};
        var checkBtn      = widget.querySelector( '.gigbuilder-check-btn' );
        var messageEl     = widget.querySelector( '.gigbuilder-message' );
        var formContainer = widget.querySelector( '.gigbuilder-form-container' );
        var loadingEl     = widget.querySelector( '.gigbuilder-loading' );
        var datePicker    = widget.querySelector( '.gigbuilder-date-picker' );
        var selectedDateEl = widget.querySelector( '.gigbuilder-selected-date' );
        var validationEl  = widget.querySelector( '.gigbuilder-validation-errors' );

        // Populate dropdowns (only present when date_input = dropdowns)
        var monthSelect = document.getElementById( 'gb-date-month' );
        var daySelect   = document.getElementById( 'gb-date-day' );
        var yearSelect  = document.getElementById( 'gb-date-year' );

        var monthNames = ['January','February','March','April','May','June',
                          'July','August','September','October','November','December'];

        if ( monthSelect && daySelect && yearSelect ) {
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
        }

        /**
         * Get the selected date in MM/DD/YYYY format.
         */
        function getSelectedDate() {
            var calendarInput = document.getElementById( 'gb-date-calendar' );

            if ( calendarInput ) {
                var val = calendarInput.value;
                if ( ! val ) return '';
                var parts = val.split( '-' );
                return parts[1] + '/' + parts[2] + '/' + parts[0];
            } else if ( monthSelect && daySelect && yearSelect ) {
                var mm = monthSelect.value;
                var dd = daySelect.value;
                var yy = yearSelect.value;
                if ( ! mm || ! dd || ! yy ) return '';
                return mm + '/' + dd + '/' + yy;
            }

            return '';
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
            if ( datePicker ) datePicker.style.display = '';
            if ( selectedDateEl ) {
                selectedDateEl.style.display = 'none';
                selectedDateEl.innerHTML = '';
            }
            if ( messageEl ) GigbuilderTools.hideMessage( messageEl );
            hideValidation();
            if ( formContainer ) formContainer.innerHTML = '';

            // Stepped: reset to step 1
            if ( isStepped ) {
                setStep( 1 );
            }
        }

        function hideValidation() {
            if ( validationEl ) {
                validationEl.style.display = 'none';
                validationEl.innerHTML = '';
            }
        }

        /**
         * Stepped flow: set which step is active.
         */
        function setStep( activeNum ) {
            if ( ! isStepped ) return;
            var steps = widget.querySelectorAll( '.gigbuilder-step' );
            for ( var i = 0; i < steps.length; i++ ) {
                var step = steps[i];
                var num = parseInt( step.getAttribute( 'data-step' ), 10 );
                step.className = step.className.replace( /gigbuilder-step--(active|completed|upcoming)/g, '' ).trim();
                if ( num < activeNum ) {
                    step.classList.add( 'gigbuilder-step--completed' );
                    // Show checkmark
                    var numEl = step.querySelector( '.gigbuilder-step-number' );
                    if ( numEl ) numEl.innerHTML = '&#10003;';
                } else if ( num === activeNum ) {
                    step.classList.add( 'gigbuilder-step--active' );
                    var numEl = step.querySelector( '.gigbuilder-step-number' );
                    if ( numEl ) numEl.textContent = String( num );
                } else {
                    step.classList.add( 'gigbuilder-step--upcoming' );
                    var numEl = step.querySelector( '.gigbuilder-step-number' );
                    if ( numEl ) numEl.textContent = String( num );
                }
            }
        }

        /**
         * Show success state (style-aware).
         */
        function showSuccess( message, dateDisplay ) {
            var html = '<div class="gigbuilder-success">'
                + '<div class="gigbuilder-success-icon">&#10003;</div>'
                + '<div class="gigbuilder-success-title">' + message + '</div>'
                + '<div class="gigbuilder-success-date">' + dateDisplay + '</div>'
                + '</div>';

            if ( isStepped ) {
                setStep( 3 );
                var step3 = widget.querySelector( '.gigbuilder-step[data-step="3"] .gigbuilder-step-content' );
                if ( step3 ) {
                    step3.innerHTML = '<div class="gigbuilder-step-title">Confirmation</div>' + html;
                }
                // Hide loading, date picker
                if ( loadingEl ) loadingEl.style.display = 'none';
                if ( datePicker ) datePicker.style.display = 'none';
                if ( formContainer ) formContainer.innerHTML = '';
            } else if ( isCard ) {
                var card = widget.querySelector( '.gigbuilder-card' );
                if ( card ) card.innerHTML = html;
            } else {
                // Minimal: replace widget content
                var heading = widget.querySelector( '.gigbuilder-heading' );
                var sub = widget.querySelector( '.gigbuilder-subheading' );
                if ( heading ) heading.style.display = 'none';
                if ( sub ) sub.style.display = 'none';
                if ( datePicker ) datePicker.style.display = 'none';
                if ( selectedDateEl ) selectedDateEl.style.display = 'none';
                if ( messageEl ) messageEl.style.display = 'none';
                if ( formContainer ) formContainer.innerHTML = '';
                // Insert success after hidden elements
                var successDiv = document.createElement( 'div' );
                successDiv.innerHTML = html;
                widget.appendChild( successDiv );
            }
        }

        // Check date button
        checkBtn.addEventListener( 'click', function() {
            var date = getSelectedDate();
            if ( ! date ) {
                if ( messageEl ) GigbuilderTools.showMessage( messageEl, 'error', 'Please select a date.' );
                if ( formContainer ) formContainer.innerHTML = '';
                return;
            }

            if ( messageEl ) GigbuilderTools.hideMessage( messageEl );
            hideValidation();
            if ( formContainer ) formContainer.innerHTML = '';
            if ( loadingEl ) loadingEl.style.display = 'block';

            var formData = new FormData();
            formData.append( 'action', 'gigbuilder_check_date' );
            formData.append( 'nonce', config.nonce );
            formData.append( 'date', date );

            fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                .then( function( res ) { return res.json(); } )
                .then( function( response ) {
                    if ( loadingEl ) loadingEl.style.display = 'none';

                    if ( ! response.success ) {
                        if ( messageEl ) GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                        return;
                    }

                    var data = response.data;

                    if ( data.status === 'available' && data.formHtml ) {
                        if ( messageEl ) GigbuilderTools.hideMessage( messageEl );

                        // Hide date picker, show selected date
                        if ( datePicker ) datePicker.style.display = 'none';

                        if ( isStepped ) {
                            // Stepped: add date summary to step 1, advance to step 2
                            var step1Content = widget.querySelector( '.gigbuilder-step[data-step="1"] .gigbuilder-step-content' );
                            if ( step1Content ) {
                                // Keep the title, add summary
                                var titleEl = step1Content.querySelector( '.gigbuilder-step-title' );
                                step1Content.innerHTML = '';
                                if ( titleEl ) step1Content.appendChild( titleEl );
                                var summaryHtml = '<div class="gigbuilder-step-date-summary">' + formatDate( date ) + '</div>'
                                    + '<div class="gigbuilder-step-status">Available</div>'
                                    + '<a href="#" class="gigbuilder-change-date" style="font-size:0.85em;opacity:0.6;">Change</a>';
                                var summaryDiv = document.createElement( 'div' );
                                summaryDiv.innerHTML = summaryHtml;
                                step1Content.appendChild( summaryDiv );

                                step1Content.querySelector( '.gigbuilder-change-date' ).addEventListener( 'click', function( e ) {
                                    e.preventDefault();
                                    showDatePicker();
                                });
                            }

                            setStep( 2 );
                            if ( formContainer ) {
                                formContainer.innerHTML = data.formHtml;
                                attachFormHandler( formContainer.querySelector( '.gigbuilder-form' ), date );
                            }
                        } else {
                            // Card / Minimal
                            if ( selectedDateEl ) {
                                selectedDateEl.innerHTML = '<strong>' + formatDate( date ) + '</strong> &nbsp; <a href="#" class="gigbuilder-change-date">Change Date</a>';
                                selectedDateEl.style.display = '';

                                selectedDateEl.querySelector( '.gigbuilder-change-date' ).addEventListener( 'click', function( e ) {
                                    e.preventDefault();
                                    showDatePicker();
                                });
                            }

                            if ( formContainer ) {
                                formContainer.innerHTML = data.formHtml;
                                attachFormHandler( formContainer.querySelector( '.gigbuilder-form' ), date );
                            }
                        }
                    } else {
                        if ( messageEl ) GigbuilderTools.showMessage( messageEl, data.status, data.message );

                        if ( isStepped && data.status === 'booked' ) {
                            // Stay on step 1 but show the message
                        }
                    }
                })
                .catch( function() {
                    if ( loadingEl ) loadingEl.style.display = 'none';
                    if ( messageEl ) GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
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
                if ( el.type === 'hidden' ) continue;

                var val = el.value ? el.value.trim() : '';
                if ( ! val ) {
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

                var missing = validateForm( formEl );
                if ( missing.length > 0 ) {
                    var html = '<strong>Please complete the following:</strong><ul>';
                    for ( var i = 0; i < missing.length; i++ ) {
                        html += '<li>' + missing[i] + '</li>';
                    }
                    html += '</ul>';
                    if ( validationEl ) {
                        validationEl.innerHTML = html;
                        validationEl.style.display = '';
                        validationEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                }

                var submitBtn = formEl.querySelector( '.gigbuilder-submit' );
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';

                var answers = GigbuilderTools.collectFormAnswers( formEl );
                if ( loadingEl ) loadingEl.style.display = 'block';

                var formData = new FormData();
                formData.append( 'action', 'gigbuilder_submit_booking' );
                formData.append( 'nonce', config.nonce );
                formData.append( 'date', date );
                formData.append( 'answers', JSON.stringify( answers ) );

                fetch( config.ajaxUrl, { method: 'POST', body: formData } )
                    .then( function( res ) { return res.json(); } )
                    .then( function( response ) {
                        if ( loadingEl ) loadingEl.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.textContent = widget.getAttribute( 'data-submit-text' ) || 'Submit';

                        if ( ! response.success ) {
                            if ( messageEl ) GigbuilderTools.showMessage( messageEl, 'error', response.data.message || 'An error occurred.' );
                            return;
                        }

                        var data = response.data;

                        if ( data.status === 'success' ) {
                            var dateDisplay = formatDate( date );
                            sessionStorage.setItem( 'gigbuilder_booked', JSON.stringify({
                                date: dateDisplay,
                                message: data.message
                            }) );
                            showSuccess( data.message, dateDisplay );
                        } else {
                            if ( messageEl ) GigbuilderTools.showMessage( messageEl, data.status, data.message );
                        }
                    })
                    .catch( function() {
                        if ( loadingEl ) loadingEl.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.textContent = widget.getAttribute( 'data-submit-text' ) || 'Submit';
                        if ( messageEl ) GigbuilderTools.showMessage( messageEl, 'error', 'Connection error. Please try again.' );
                    });
            });
        }
    });
})();
