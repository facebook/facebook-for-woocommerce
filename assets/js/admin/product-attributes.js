/**
 * Product Attributes Settings Page Scripts
 *
 * @package facebook-for-woocommerce
 */

jQuery(document).ready(function($) {
    // Fix duplicated content issue
    var $infoNotes = $('.wc-facebook-info-note');
    if ($infoNotes.length > 1) {
        $infoNotes.not(':first').closest('tr').remove();
    }
    
    // Fix duplicated mapping tables
    var $mappingRows = $('th.titledesc:contains("WooCommerce to Facebook Field Mapping")').closest('tr');
    if ($mappingRows.length > 1) {
        $mappingRows.not(':first').remove();
    }
    
    // Initialize select2 for attribute and field dropdowns
    function initializeSelects() {
        if ($.fn.select2) {
            $('.wc-attribute-search, .fb-field-search').select2({
                width: '100%',
                placeholder: function() {
                    return $(this).data('placeholder');
                }
            });
        }
    }
    
    // Initialize on page load
    initializeSelects();

    // Fix form field names when a mapping row changes
    $('#facebook-attribute-mapping-table').on('change', '.wc-attribute-search', function() {
        var $select = $(this);
        var $row = $select.closest('tr');
        var attribute = $select.val();
        
        // Update the name attributes of other fields in the row
        if (attribute) {
            $row.find('.fb-field-search').attr('name', 'wc_facebook_field_mapping[' + attribute + ']');
            $row.find('.fb-default-value').attr('name', 'wc_facebook_attribute_default[' + attribute + ']');
        } else {
            // If no attribute is selected, use an empty name to prevent conflicts
            $row.find('.fb-field-search').attr('name', 'wc_facebook_field_mapping[]');
            $row.find('.fb-default-value').attr('name', 'wc_facebook_attribute_default[]');
        }
    });
    
    // Add new mapping row in edit mode
    $('.add-new-mapping').on('click', function() {
        var $lastRow = $('#facebook-attribute-mapping-table tbody tr:last-child');
        var $newRow = $lastRow.clone();
        
        // Clear values
        $newRow.find('select').val('').trigger('change');
        $newRow.find('input[type="text"]').val('');
        $newRow.find('input[type="hidden"]').remove();
        
        // Reinitialize select2
        if ($.fn.select2) {
            $newRow.find('select').select2('destroy');
        }
        
        // Append to table
        $('#facebook-attribute-mapping-table tbody').append($newRow);
        
        // Reinitialize select2 for the new row
        initializeSelects();
    });
    
    // Add new mapping from view mode - redirects to edit mode with a new empty row
    $('.add-mapping-button').on('click', function(e) {
        // Use the href attribute directly, which has been set with all necessary parameters
        window.location.href = $(this).attr('href');
        e.preventDefault();
    });
    
    // Remove mapping row
    $('#facebook-attribute-mapping-table').on('click', '.remove-mapping-row', function(e) {
        e.preventDefault();
        
        // Don't remove if it's the only row
        if ($('#facebook-attribute-mapping-table tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            // Clear values instead
            $(this).closest('tr').find('select').val('').trigger('change');
            $(this).closest('tr').find('input[type="text"]').val('');
            $(this).closest('tr').find('input[type="hidden"]').remove();
        }
    });
}); 