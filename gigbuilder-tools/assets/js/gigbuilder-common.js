/**
 * Gigbuilder Tools — shared utilities
 */
var GigbuilderTools = (function() {
    'use strict';

    /**
     * Show a status message in a container element.
     */
    function showMessage( container, status, message ) {
        container.style.display = 'block';
        container.className = 'gigbuilder-message gigbuilder-message--' + status;
        container.innerHTML = message;
    }

    /**
     * Hide a message container.
     */
    function hideMessage( container ) {
        container.style.display = 'none';
        container.innerHTML = '';
    }

    /**
     * Collect form answers from a gigbuilder-form into the API format.
     * Returns array of {name, value} objects.
     */
    function collectFormAnswers( formEl ) {
        var answers = [];
        var inputs = formEl.querySelectorAll( 'input, select, textarea' );

        for ( var i = 0; i < inputs.length; i++ ) {
            var el = inputs[i];
            var name = el.name;
            if ( ! name || el.type === 'submit' ) continue;

            // Radio: only include checked
            if ( el.type === 'radio' && ! el.checked ) continue;

            // Checkbox: collect all checked values into one answer
            if ( el.type === 'checkbox' ) {
                if ( el.checked ) {
                    // Check if we already started this name
                    var existing = null;
                    for ( var j = 0; j < answers.length; j++ ) {
                        if ( answers[j].name === name.replace( '[]', '' ) ) {
                            existing = answers[j];
                            break;
                        }
                    }
                    if ( existing ) {
                        existing.value += ', ' + el.value;
                    } else {
                        answers.push({ name: name.replace( '[]', '' ), value: el.value });
                    }
                }
                continue;
            }

            answers.push({ name: name, value: el.value });
        }

        return answers;
    }

    /**
     * Update a hidden time field from its hour/min/ampm selects.
     */
    function updateTimeField( fieldId ) {
        var hour = document.getElementById( fieldId + '-hour' ).value;
        var min  = document.getElementById( fieldId + '-min' ).value;
        var ampm = document.getElementById( fieldId + '-ampm' ).value;
        var hidden = document.getElementById( fieldId );
        if ( hour && min ) {
            hidden.value = hour + ':' + min + ' ' + ampm;
        } else {
            hidden.value = '';
        }
    }

    /**
     * Update a hidden duration field from its hour/min selects.
     */
    function updateDurationField( fieldId ) {
        var hour = document.getElementById( fieldId + '-hour' ).value;
        var min  = document.getElementById( fieldId + '-min' ).value;
        var hidden = document.getElementById( fieldId );
        if ( hour && min ) {
            hidden.value = hour + ':' + min;
        } else {
            hidden.value = '';
        }
    }

    /**
     * Show/hide the location name input based on selected value.
     * '1' = Private Residence, '999' = Not Found — both need a name.
     */
    function handleLocationChange( fieldId ) {
        var select = document.getElementById( fieldId );
        var wrap   = document.getElementById( fieldId + '-name-wrap' );
        var input  = document.getElementById( fieldId + '-name' );
        var val    = select.value;

        if ( val === '1' || val === '999' ) {
            wrap.style.display = '';
            input.placeholder = val === '1'
                ? 'Enter address if different from client'
                : 'Enter location name or address';
        } else {
            wrap.style.display = 'none';
            input.value = '';
        }
    }

    return {
        showMessage: showMessage,
        hideMessage: hideMessage,
        collectFormAnswers: collectFormAnswers,
        updateTimeField: updateTimeField,
        updateDurationField: updateDurationField,
        handleLocationChange: handleLocationChange
    };
})();
