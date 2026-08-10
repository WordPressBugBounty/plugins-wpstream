/*
 * Gradient picker script for the theme's button styling admin control.
 *
 * Wires up two WordPress color pickers plus an angle slider into a live
 * linear-gradient preview. Whenever any of the three inputs change, it rebuilds
 * the `linear-gradient(...)` CSS string, paints the #gradient-preview swatch,
 * and stores the same value in the hidden #wpstream_type_2_button_background_color
 * field (triggering change) so it is saved as the type-2 button background.
 */

// Run once the DOM is ready; `$` is the jQuery instance passed in.
jQuery(document).ready(function($) {
    // Initialize the color pickers
    $('.color-picker').wpColorPicker();

    /**
     * Rebuild the gradient string from the current inputs and apply it to both
     * the preview swatch and the hidden saved-value field.
     */
    // Function to update the gradient preview
    function updateGradient() {
        // Read the current angle (degrees) and the two picker colors.
        var angle = $('#gradient-angle-slider').val();
        var color1 = $('#gradient-color1').wpColorPicker('color');
        var color2 = $('#gradient-color2').wpColorPicker('color');
        // Paint the preview box with the composed linear gradient.
        $('#gradient-preview').css('background', 'linear-gradient(' + angle + 'deg, ' + color1 + ', ' + color2 + ')');
        // Store the same gradient in the hidden field WordPress will save.
        $('#wpstream_type_2_button_background_color').val('linear-gradient(' + angle + 'deg, ' + color1 + ', ' + color2 + ')').trigger('change');
    }

    // Update the gradient preview on color change
    // Re-run the preview whenever the angle or either color changes.
    $('#gradient-angle-slider, #gradient-color1, #gradient-color2').on('change', updateGradient);

    // Initial update of the gradient preview
    // Paint the preview once on load to reflect the saved values.
    updateGradient();
});