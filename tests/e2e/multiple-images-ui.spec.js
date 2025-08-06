/**
 * End-to-end tests for Multiple Images UI functionality
 * 
 * These tests simulate real user interactions with the multiple images feature
 * in the WooCommerce product editor.
 * 
 * @requires Playwright or similar E2E testing framework
 */

const { test, expect } = require('@playwright/test');

test.describe('Multiple Images UI Interactions', () => {

    let adminUrl;
    let productId;
    
    test.beforeEach(async ({ page }) => {
        // Setup: Create a variable product with variations
        const baseUrl = process.env.WORDPRESS_URL || 'http://localhost';
        adminUrl = `${baseUrl}/wp-admin`;
        
        // Login as admin
        await page.goto(`${adminUrl}/wp-login.php`);
        await page.fill('#user_login', process.env.WP_USERNAME || 'admin');
        await page.fill('#user_pass', process.env.WP_PASSWORD || 'password');
        await page.click('#wp-submit');
        
        // Create a new variable product for testing
        await page.goto(`${adminUrl}/post-new.php?post_type=product`);
        await page.fill('#title', 'Test Variable Product - Multiple Images');
        
        // Set product type to variable
        await page.selectOption('#product-type', 'variable');
        
        // Add some attributes and variations (simplified for E2E testing)
        await page.click('.attribute_tab');
        await page.click('.add_attribute');
        await page.fill('[name="attribute_names[0]"]', 'Color');
        await page.fill('[name="attribute_values[0]"]', 'Red | Blue | Green');
        await page.check('[name="attribute_variation[0]"]');
        await page.click('.save_attributes');
        
        // Wait for attributes to be saved
        await page.waitForTimeout(2000);
        
        // Generate variations
        await page.click('.variations_tab');
        await page.click('.generate_variations');
        await page.click('.do_variation_action');
        
        // Wait for variations to be generated
        await page.waitForTimeout(3000);
        
        // Save the product
        await page.click('#publish');
        await page.waitForSelector('#message');
        
        // Get product ID from URL
        const url = page.url();
        productId = url.match(/post=(\d+)/)?.[1];
    });

    test.afterEach(async ({ page }) => {
        // Cleanup: Delete the test product
        if (productId) {
            await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
            await page.click('#delete-action a');
            await page.click('#submit');
        }
    });

    test('Should show multiple images option in variations', async ({ page }) => {
        // Navigate to the product edit page
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        
        // Go to variations tab
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand first variation
        await page.click('.woocommerce_variation .handlediv');
        await page.waitForTimeout(1000);
        
        // Click on Facebook tab within the variation
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.waitForTimeout(1000);
        
        // Verify Facebook Product Image options are present
        await expect(page.locator('input[value="product"]')).toBeVisible();
        await expect(page.locator('input[value="parent_product"]')).toBeVisible();
        await expect(page.locator('input[value="custom"]')).toBeVisible();
        await expect(page.locator('input[value="multiple"]')).toBeVisible();
        
        // Verify "Use multiple images" option text
        await expect(page.locator('text=Use multiple images')).toBeVisible();
    });

    test('Should show/hide multiple images field based on selection', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand first variation and Facebook section
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.waitForTimeout(1000);
        
        // Initially, multiple images field should be hidden
        await expect(page.locator('.fb-open-images-library')).toBeHidden();
        
        // Select "Use multiple images" option
        await page.check('input[value="multiple"]');
        await page.waitForTimeout(500);
        
        // Multiple images field should now be visible
        await expect(page.locator('.fb-open-images-library')).toBeVisible();
        await expect(page.locator('text=Choose Multiple Images')).toBeVisible();
        
        // Select a different option
        await page.check('input[value="product"]');
        await page.waitForTimeout(500);
        
        // Multiple images field should be hidden again
        await expect(page.locator('.fb-open-images-library')).toBeHidden();
    });

    test('Should open media library when clicking Choose Multiple Images', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and select multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        await page.waitForTimeout(1000);
        
        // Click "Choose Multiple Images" button
        await page.click('.fb-open-images-library');
        await page.waitForTimeout(2000);
        
        // Verify media library modal opened
        await expect(page.locator('.media-modal')).toBeVisible();
        await expect(page.locator('.media-modal-title')).toContainText('Choose Multiple Images');
        
        // Verify it's set to allow multiple selection
        await expect(page.locator('.media-toolbar .media-toolbar-primary .select-mode-toggle-button')).toBeVisible();
    });

    test('Should display selected images as thumbnails', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and select multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        await page.waitForTimeout(1000);
        
        // Open media library
        await page.click('.fb-open-images-library');
        await page.waitForTimeout(2000);
        
        // Select some images (assuming test media exists)
        if (await page.locator('.attachment').first().isVisible()) {
            await page.locator('.attachment').first().click();
            await page.waitForTimeout(500);
            
            if (await page.locator('.attachment').nth(1).isVisible()) {
                await page.locator('.attachment').nth(1).click();
                await page.waitForTimeout(500);
            }
            
            // Click Select button
            await page.click('.media-button-select');
            await page.waitForTimeout(2000);
            
            // Verify thumbnails appear
            await expect(page.locator('.fb-product-images-thumbnails .image-thumbnail')).toHaveCount(2);
            await expect(page.locator('.fb-product-images-thumbnails img')).toHaveCount(2);
            await expect(page.locator('.remove-image')).toHaveCount(2);
        }
    });

    test('Should remove individual images when clicking Remove', async ({ page }) => {
        // Setup with pre-selected images (simulate existing data)
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and select multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        
        // Simulate having 3 images by injecting HTML (for testing purposes)
        await page.evaluate(() => {
            const container = document.querySelector('.fb-product-images-thumbnails');
            const hiddenField = document.querySelector('[name*="variable_fb_product_images"]');
            
            if (container && hiddenField) {
                container.innerHTML = `
                    <p class="form-field image-thumbnail">
                        <img src="/wp-content/uploads/test1.jpg">
                        <span data-attachment-id="101">test1.jpg</span>
                        <a href="#" class="remove-image" data-attachment-id="101">Remove</a>
                    </p>
                    <p class="form-field image-thumbnail">
                        <img src="/wp-content/uploads/test2.jpg">
                        <span data-attachment-id="102">test2.jpg</span>
                        <a href="#" class="remove-image" data-attachment-id="102">Remove</a>
                    </p>
                    <p class="form-field image-thumbnail">
                        <img src="/wp-content/uploads/test3.jpg">
                        <span data-attachment-id="103">test3.jpg</span>
                        <a href="#" class="remove-image" data-attachment-id="103">Remove</a>
                    </p>
                `;
                hiddenField.value = '101,102,103';
            }
        });
        
        await page.waitForTimeout(1000);
        
        // Verify 3 images are present
        await expect(page.locator('.image-thumbnail')).toHaveCount(3);
        
        // Click remove on the second image
        await page.locator('.remove-image[data-attachment-id="102"]').click();
        await page.waitForTimeout(500);
        
        // Verify image was removed
        await expect(page.locator('.image-thumbnail')).toHaveCount(2);
        await expect(page.locator('[data-attachment-id="102"]')).toHaveCount(0);
        
        // Verify hidden field was updated
        const hiddenFieldValue = await page.locator('[name*="variable_fb_product_images"]').inputValue();
        expect(hiddenFieldValue).toBe('101,103');
    });

    test('Should persist data after saving product', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and configure multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        
        // Manually set hidden field value (simulating image selection)
        await page.evaluate(() => {
            const hiddenField = document.querySelector('[name*="variable_fb_product_images"]');
            if (hiddenField) {
                hiddenField.value = '201,202,203';
            }
        });
        
        // Save the product
        await page.click('#publish');
        await page.waitForSelector('#message');
        
        // Reload the page
        await page.reload();
        await page.waitForLoadState('networkidle');
        
        // Navigate back to variations and verify data persisted
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        
        // Verify "Use multiple images" is still selected
        await expect(page.locator('input[value="multiple"]')).toBeChecked();
        
        // Verify hidden field still contains our data
        const persistedValue = await page.locator('[name*="variable_fb_product_images"]').inputValue();
        expect(persistedValue).toBe('201,202,203');
    });

    test('Should handle multiple variations independently', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Get all variations
        const variations = await page.locator('.woocommerce_variation').count();
        
        if (variations >= 2) {
            // Configure first variation
            await page.locator('.woocommerce_variation').nth(0).locator('.handlediv').click();
            await page.locator('.woocommerce_variation').nth(0).locator('.facebook-metabox .handlediv').click();
            await page.locator('.woocommerce_variation').nth(0).locator('input[value="multiple"]').check();
            
            await page.evaluate((index) => {
                const hiddenField = document.querySelector(`[name="variable_fb_product_images${index}"]`);
                if (hiddenField) {
                    hiddenField.value = '301,302';
                }
            }, 0);
            
            // Configure second variation differently
            await page.locator('.woocommerce_variation').nth(1).locator('.handlediv').click();
            await page.locator('.woocommerce_variation').nth(1).locator('.facebook-metabox .handlediv').click();
            await page.locator('.woocommerce_variation').nth(1).locator('input[value="multiple"]').check();
            
            await page.evaluate((index) => {
                const hiddenField = document.querySelector(`[name="variable_fb_product_images${index}"]`);
                if (hiddenField) {
                    hiddenField.value = '401,402,403';
                }
            }, 1);
            
            // Verify each variation has independent data
            const firstVariationValue = await page.locator('[name="variable_fb_product_images0"]').inputValue();
            const secondVariationValue = await page.locator('[name="variable_fb_product_images1"]').inputValue();
            
            expect(firstVariationValue).toBe('301,302');
            expect(secondVariationValue).toBe('401,402,403');
            expect(firstVariationValue).not.toBe(secondVariationValue);
        }
    });

    test('Should show help tip for multiple images field', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and select multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        await page.waitForTimeout(1000);
        
        // Verify help tip is present
        await expect(page.locator('.woocommerce-help-tip')).toBeVisible();
        
        // Hover over help tip to see tooltip (if implemented)
        await page.hover('.woocommerce-help-tip');
        await page.waitForTimeout(500);
        
        // Could check for tooltip content if it appears
        // This depends on how WooCommerce implements help tips
    });

    test('Should maintain UI state when switching between variations', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        const variations = await page.locator('.woocommerce_variation').count();
        
        if (variations >= 2) {
            // Configure first variation with multiple images
            await page.locator('.woocommerce_variation').nth(0).locator('.handlediv').click();
            await page.locator('.woocommerce_variation').nth(0).locator('.facebook-metabox .handlediv').click();
            await page.locator('.woocommerce_variation').nth(0).locator('input[value="multiple"]').check();
            
            // Switch to second variation and configure differently
            await page.locator('.woocommerce_variation').nth(1).locator('.handlediv').click();
            await page.locator('.woocommerce_variation').nth(1).locator('.facebook-metabox .handlediv').click();
            await page.locator('.woocommerce_variation').nth(1).locator('input[value="product"]').check();
            
            // Switch back to first variation
            await page.locator('.woocommerce_variation').nth(0).locator('.handlediv').click();
            
            // Verify first variation still has "multiple" selected
            await expect(page.locator('.woocommerce_variation').nth(0).locator('input[value="multiple"]')).toBeChecked();
            await expect(page.locator('.woocommerce_variation').nth(0).locator('.fb-open-images-library')).toBeVisible();
            
            // Switch back to second variation
            await page.locator('.woocommerce_variation').nth(1).locator('.handlediv').click();
            
            // Verify second variation still has "product" selected
            await expect(page.locator('.woocommerce_variation').nth(1).locator('input[value="product"]')).toBeChecked();
            await expect(page.locator('.woocommerce_variation').nth(1).locator('.fb-open-images-library')).toBeHidden();
        }
    });

    test('Should handle error states gracefully', async ({ page }) => {
        await page.goto(`${adminUrl}/post.php?post=${productId}&action=edit`);
        await page.click('.variations_tab');
        await page.waitForSelector('.woocommerce_variation');
        
        // Expand variation and select multiple images
        await page.click('.woocommerce_variation .handlediv');
        await page.click('.woocommerce_variation .facebook-metabox .handlediv');
        await page.check('input[value="multiple"]');
        
        // Test clicking button without wp.media available (simulate error)
        await page.evaluate(() => {
            // Temporarily disable wp.media
            window.wpMediaBackup = window.wp?.media;
            if (window.wp) {
                window.wp.media = undefined;
            }
        });
        
        // Click the button - should not crash
        await page.click('.fb-open-images-library');
        await page.waitForTimeout(1000);
        
        // Verify no modal opened (since wp.media is unavailable)
        await expect(page.locator('.media-modal')).toHaveCount(0);
        
        // Restore wp.media
        await page.evaluate(() => {
            if (window.wpMediaBackup && window.wp) {
                window.wp.media = window.wpMediaBackup;
            }
        });
    });
});