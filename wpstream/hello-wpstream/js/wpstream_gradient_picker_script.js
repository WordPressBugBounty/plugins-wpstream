jQuery(document).ready(function($) {
    // Initialize the color pickers
    $('.color-picker').wpColorPicker();

    // Function to update the gradient preview
    function updateGradient() {
        var angle = $('#gradient-angle-slider').val();
        var color1 = $('#gradient-color1').wpColorPicker('color');
        var color2 = $('#gradient-color2').wpColorPicker('color');
        $('#gradient-preview').css('background', 'linear-gradient(' + angle + 'deg, ' + color1 + ', ' + color2 + ')');
        $('#wpstream_type_2_button_background_color').val('linear-gradient(' + angle + 'deg, ' + color1 + ', ' + color2 + ')').trigger('change');
    }

    // Update the gradient preview on color change
    $('#gradient-angle-slider, #gradient-color1, #gradient-color2').on('change', updateGradient);

    // Initial update of the gradient preview
    updateGradient();
});