<?php
/**
 * This script fixes incorrect default attribute values in the Facebook for WooCommerce plugin.
 * 
 * It looks for defaults that are incorrectly mapped (like brand -> all ages)
 * and corrects them to appropriate values.
 */

// Ensure WP is loaded
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

// Check if we're logged in as admin
if (!current_user_can('manage_options')) {
    die('You need to be logged in as an administrator to run this script');
}

echo "<h1>Facebook for WooCommerce - Fix Default Attribute Values</h1>";

// Get current defaults
$current_defaults = get_option('wc_facebook_attribute_defaults', array());

echo "<h2>Current Default Values:</h2>";
echo "<pre>";
print_r($current_defaults);
echo "</pre>";

// Define the correct mapping for common attributes
$correct_defaults = array(
    // Standard attributes with correct defaults
    'pa_brand' => 'Nike', // Example brand
    'pa_age-group' => 'kids', // Valid age group
    'pa_gender' => 'unisex', // Valid gender
    'pa_condition' => 'new', // Valid condition
);

// Analyze current defaults for issues
$issues_found = false;
$issues = array();

foreach ($current_defaults as $attr => $value) {
    // Check for mismatched brand value
    if (stripos($attr, 'brand') !== false) {
        if (in_array(strtolower($value), array('male', 'female', 'unisex', 'all ages', 'kids', 'adult', 'teen', 'toddler', 'infant', 'newborn'))) {
            $issues[] = "Brand attribute '{$attr}' has value '{$value}' which looks like a gender or age group value";
            $issues_found = true;
        }
    }
    
    // Check for mismatched gender value
    if (stripos($attr, 'gender') !== false) {
        if (!in_array(strtolower($value), array('male', 'female', 'unisex'))) {
            $issues[] = "Gender attribute '{$attr}' has invalid value '{$value}' - should be male, female, or unisex";
            $issues_found = true;
        }
    }
    
    // Check for mismatched age_group value
    if (stripos($attr, 'age') !== false) {
        if (!in_array(strtolower($value), array('adult', 'all ages', 'teen', 'kids', 'toddler', 'infant', 'newborn'))) {
            $issues[] = "Age group attribute '{$attr}' has invalid value '{$value}' - should be a valid age group";
            $issues_found = true;
        }
    }
}

if ($issues_found) {
    echo "<h2>Issues Found:</h2>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>" . esc_html($issue) . "</li>";
    }
    echo "</ul>";
    
    echo "<h2>Fixing Issues...</h2>";
    
    // Create fixed defaults
    $fixed_defaults = array();
    
    foreach ($current_defaults as $attr => $value) {
        // Fix brand with age or gender values
        if (stripos($attr, 'brand') !== false) {
            if (in_array(strtolower($value), array('male', 'female', 'unisex', 'all ages', 'kids', 'adult', 'teen', 'toddler', 'infant', 'newborn'))) {
                // Replace with a valid brand
                $fixed_defaults[$attr] = isset($correct_defaults['pa_brand']) ? $correct_defaults['pa_brand'] : 'Nike';
                echo "<p>Fixed brand attribute '{$attr}' from '{$value}' to '{$fixed_defaults[$attr]}'</p>";
            } else {
                $fixed_defaults[$attr] = $value; // Keep valid value
            }
        }
        // Fix gender values
        else if (stripos($attr, 'gender') !== false) {
            if (!in_array(strtolower($value), array('male', 'female', 'unisex'))) {
                // Replace with a valid gender
                $fixed_defaults[$attr] = isset($correct_defaults['pa_gender']) ? $correct_defaults['pa_gender'] : 'unisex';
                echo "<p>Fixed gender attribute '{$attr}' from '{$value}' to '{$fixed_defaults[$attr]}'</p>";
            } else {
                $fixed_defaults[$attr] = $value; // Keep valid value
            }
        }
        // Fix age group values
        else if (stripos($attr, 'age') !== false) {
            if (!in_array(strtolower($value), array('adult', 'all ages', 'teen', 'kids', 'toddler', 'infant', 'newborn'))) {
                // Replace with a valid age group
                $fixed_defaults[$attr] = isset($correct_defaults['pa_age-group']) ? $correct_defaults['pa_age-group'] : 'kids';
                echo "<p>Fixed age group attribute '{$attr}' from '{$value}' to '{$fixed_defaults[$attr]}'</p>";
            } else {
                $fixed_defaults[$attr] = $value; // Keep valid value
            }
        }
        // Keep other values as is
        else {
            $fixed_defaults[$attr] = $value;
        }
    }
    
    // Save the fixed defaults
    update_option('wc_facebook_attribute_defaults', $fixed_defaults);
    
    echo "<h2>Updated Default Values:</h2>";
    echo "<pre>";
    print_r($fixed_defaults);
    echo "</pre>";
    
    echo "<p>Default values have been fixed! You can now run your product sync and attributes should map correctly.</p>";
} else {
    echo "<p>No issues found with default values.</p>";
}

echo "<p><a href='admin.php?page=wc-facebook'>Return to Facebook for WooCommerce settings</a></p>"; 