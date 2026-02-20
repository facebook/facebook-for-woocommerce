const { test, expect } = require('@playwright/test');
const {
    TIMEOUTS,
    baseURL,
    loginToWordPress,
    safeScreenshot,
    cleanupProduct,
    generateUniqueSKU,
    logTestStart,
    logTestEnd,
    validateFacebookSync,
    validateCategorySync,
    createTestProduct,
    createTestCategory,
    cleanupCategory,
    execWP
} = require('./helpers/js');

test.describe('Meta for WooCommerce - Product Category E2E Tests', () => {

    test.beforeEach(async ({ page }, testInfo) => {
        // Log test start first for proper chronological order
        logTestStart(testInfo);

        // Ensure browser stability
        await page.setViewportSize({ width: 1280, height: 720 });
        await loginToWordPress(page);
    });

    test('Create category and sync products to catalog as set', async ({ page }, testInfo) => {
        let product1Id = null;
        let product2Id = null;
        let categoryId = null;

        try {
            // Create test products using createTestProduct function
            console.log('📦 Creating test products...');
            const [product1, product2] = await Promise.all([
                createTestProduct({
                    productType: 'simple',
                    price: '24.99',
                    stock: '10'
                }),
                createTestProduct({
                    productType: 'variable',
                    price: '34.99',
                    stock: '15'
                })
            ]);
            product1Id = product1.productId;
            product2Id = product2.productId;
            console.log(`✅ Created test products: ${product1Id}, ${product2Id}`);

            // Navigate to Categories page
            await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });
            console.log('✅ Navigated to Categories page');

            // Generate unique category name
            const categoryName = generateUniqueSKU('Category');
            const categoryDescription = 'This is a test category for E2E testing';

            // Enter category data
            const categoryNameField = page.locator('#tag-name');
            await categoryNameField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await categoryNameField.fill(categoryName);
            console.log(`✅ Entered category name: ${categoryName}`);

            // Enter category description
            const categoryDescField = page.locator('#tag-description');
            await categoryDescField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await categoryDescField.fill(categoryDescription);
            console.log('✅ Entered category description');

            // Click 'Add new category' button
            const addCategoryBtn = page.locator('#submit');
            await addCategoryBtn.click();
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Clicked Add new category button');

            // Extract category ID from the page
            const categoryRow = page.locator(`tr:has-text("${categoryName}")`).first();
            await categoryRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            const categoryLink = categoryRow.locator('a.row-title').first();
            const categoryHref = await categoryLink.getAttribute('href');
            const categoryIdMatch = categoryHref.match(/tag_ID=(\d+)/);
            categoryId = categoryIdMatch ? parseInt(categoryIdMatch[1]) : null;
            console.log(`✅ Category created with ID: ${categoryId}`);

            // Navigate to All Products tab
            await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });
            console.log('✅ Navigated to All Products page');

            // Select the products using checkboxes
            const product1Checkbox = page.locator(`#cb-select-${product1Id}`);
            const product2Checkbox = page.locator(`#cb-select-${product2Id}`);

            await product1Checkbox.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await product1Checkbox.check();
            console.log(`✅ Selected product ${product1Id}`);

            await product2Checkbox.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await product2Checkbox.check();
            console.log(`✅ Selected product ${product2Id}`);

            // Choose 'Edit' from Bulk Actions dropdown
            const bulkActionsDropdown = page.locator('#bulk-action-selector-top');
            await bulkActionsDropdown.selectOption('edit');
            console.log('✅ Selected Edit from Bulk Actions');

            // Click Apply button
            const applyBtn = page.locator('#doaction');
            await applyBtn.click();
            console.log('✅ Clicked Apply button');

            // Wait for bulk edit interface to appear
            const bulkEditRow = page.locator('#bulk-edit');
            await bulkEditRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            console.log('✅ Bulk edit interface opened');

            // Click the newly created category checkbox in Product categories section
            const categoryCheckbox = page.getByRole('checkbox', { name: categoryName });
            await categoryCheckbox.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await categoryCheckbox.check();
            console.log(`✅ Checked category ${categoryId} checkbox`);

            // Click Update button in bulk edit
            const updateBtn = page.locator('#bulk_edit');
            await updateBtn.click();
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Clicked Update button');

            // Validate that the category has been synced as a set
            // verify that the products are still synced and belong to the category
            const [product1Result, product2Result, categoryResult] = await Promise.all([
                validateFacebookSync(product1Id, product1.productName, 5),
                validateFacebookSync(product2Id, product2.productName, 5, 8),
                validateCategorySync(categoryId, categoryName, 5)
            ]);

            expect(categoryResult['success']).toBe(true);
            console.log(categoryResult['raw_data']['facebook_data']);
            console.log('✅ Category sync validated');
            expect(product1Result['success']).toBe(true);
            console.log(product1Result['raw_data']['facebook_data'][0]['product_sets']);
            const isProduct1InCorrectProductSet = product1Result['raw_data']['facebook_data'][0]['product_sets'].some(
                set => {
                    return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(categoryResult['facebook_product_set_id'])
                }
            );
            expect(isProduct1InCorrectProductSet).toBe(true);
            console.log('✅ Product 1 sync validated');
            expect(product2Result['success']).toBe(true);
            const isProduct2InCorrectProductSet = product2Result['raw_data']['facebook_data'].some(
                product => {
                    return product['product_sets'].some(
                        set => {
                            console.log(set);
                            return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(categoryResult['facebook_product_set_id'])
                        }
                    )
                }
            );
            expect(isProduct2InCorrectProductSet).toBe(true);
            console.log('✅ Product 2 sync validated');

            await page.goto(`${baseURL}/wp-admin/post.php?post=${product1Id}&action=edit`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });

            const isCategoryChecked = await categoryCheckbox.isChecked();
            expect(isCategoryChecked).toBe(true);
            console.log('✅ Verified product 1 has category assigned');

            console.log('✅ Category and product sync test completed successfully');
            logTestEnd(testInfo, true);

        } catch (error) {
            console.log(`⚠️ Category sync test failed: ${error.message}`);
            await safeScreenshot(page, 'category-sync-test-failure.png');
            logTestEnd(testInfo, false);
            throw error;
        } finally {
            await Promise.all([
                product1Id ? cleanupProduct(product1Id) : Promise.resolve(),
                product2Id ? cleanupProduct(product2Id) : Promise.resolve(),
                categoryId ? cleanupCategory(categoryId) : Promise.resolve()
            ]);
        }
    });

    test('Update existing category and verify Facebook sync', async ({ page }, testInfo) => {
        let product1Id = null;
        let product2Id = null;
        let categoryId = null;

        try {
            // Step 1: Create a test category via API
            console.log('📁 Creating test category via API...');
            const categoryData = await createTestCategory({
                description: 'Test category for name update testing'
            });
            categoryId = categoryData.categoryId;
            const originalCategoryName = categoryData.categoryName;

            // Step 2: Create first product and attach it to the category via API
            console.log('📦 Creating first product and attaching to category via API...');
            const product1 = await createTestProduct({
                productType: 'simple',
                price: '19.99',
                stock: '12',
                categoryIds: [categoryId]
            });
            product1Id = product1.productId;
            console.log(`✅ Created product 1: ${product1.productName} (ID: ${product1Id}) with category ${categoryId}`);

            // Step 3: Validate category sync with one product
            console.log('🔍 Validating initial sync...');
            const initialCategoryResult = await validateCategorySync(categoryId, originalCategoryName, 30);
            expect(initialCategoryResult['success']).toBe(true);
            console.log('✅ Initial category sync validated with one product');

            // Store the Facebook product set ID for later verification
            const facebookProductSetId = initialCategoryResult['facebook_product_set_id'];
            console.log(`📊 Facebook Product Set ID: ${facebookProductSetId}`);

            // Validate product 1 is in the category
            const product1InitialResult = await validateFacebookSync(product1Id, product1.productName, 30);
            expect(product1InitialResult['success']).toBe(true);
            const isProduct1InInitialSet = product1InitialResult['raw_data']['facebook_data'][0]['product_sets'].some(
                set => Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
            );
            expect(isProduct1InInitialSet).toBe(true);
            console.log('✅ Product 1 validated in category product set');

            // Step 4: Update category name via UI
            console.log(`📝 Updating category name via UI...`);
            const updatedCategoryName = generateUniqueSKU('UpdatedCategory');

            // Navigate to Categories page
            await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });
            console.log('✅ Navigated to Categories page');

            // Click on the category row to edit
            const categoryRow = page.locator(`tr#tag-${categoryId}`);
            await categoryRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            const editLink = categoryRow.locator('a.row-title').first();
            await editLink.click();
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Opened category edit page');

            // Update the category name
            const categoryNameField = page.locator('#name');
            await categoryNameField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await categoryNameField.clear();
            await categoryNameField.fill(updatedCategoryName);
            console.log(`✅ Entered new category name: ${updatedCategoryName}`);

            // Click Update button to save changes
            const updateBtn = page.getByRole('button', { name: 'Update' });
            await updateBtn.click();
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Category name updated in UI');

            // Step 5: Create second product and add it to the category
            console.log('📦 Creating second product and adding to category...');
            const product2 = await createTestProduct({
                productType: 'simple',
                price: '29.99',
                stock: '8',
                categoryIds: [categoryId]
            });
            product2Id = product2.productId;

            // Step 6: Validate the name change synced to Facebook AND both products are in the category
            console.log('🔍 Step 6: Validating category name change synced to Facebook...');
            const updatedCategoryResult = await validateCategorySync(categoryId, updatedCategoryName, 5);

            // Verify category sync with updated name
            expect(updatedCategoryResult['success']).toBe(true);
            console.log('📊 Updated Category Facebook data:');
            console.log(updatedCategoryResult['raw_data']['facebook_data']);
            console.log('✅ Updated category name sync validated');

            // Verify the product set ID remains the same
            expect(updatedCategoryResult['facebook_product_set_id']).toBe(facebookProductSetId);
            console.log('✅ Verified product set ID remained consistent after name change');

            // Verify the name is updated in Facebook
            const facebookCategoryName = updatedCategoryResult['raw_data']['facebook_data']['name'];
            expect(facebookCategoryName).toBe(updatedCategoryName);
            console.log(`✅ Verified category name updated in Facebook: "${facebookCategoryName}"`);

            // Verify both products are in the updated category
            console.log('🔍 Verifying both products are in the updated category...');
            const [finalProduct1Result, finalProduct2Result] = await Promise.all([
                validateFacebookSync(product1Id, product1.productName, 30),
                validateFacebookSync(product2Id, product2.productName, 30)
            ]);

            // Verify product 1 is still in the category
            expect(finalProduct1Result['success']).toBe(true);
            const isProduct1InSet = finalProduct1Result['raw_data']['facebook_data'][0]['product_sets'].some(
                set => {
                    return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
                }
            );
            expect(isProduct1InSet).toBe(true);
            console.log('✅ Product 1 still in the updated category');

            // Verify product 2 is in the category
            expect(finalProduct2Result['success']).toBe(true);
            const isProduct2InSet = finalProduct2Result['raw_data']['facebook_data'][0]['product_sets'].some(
                set => {
                    return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
                }
            );
            expect(isProduct2InSet).toBe(true);
            console.log('✅ Product 2 is now in the updated category');
            logTestEnd(testInfo, true);
        } catch (error) {
            console.log(`⚠️ Update category test failed: ${error.message}`);
            await safeScreenshot(page, 'update-category-name-test-failure.png');
            logTestEnd(testInfo, false);
            throw error;
        } finally {
            await Promise.all([
                product1Id ? cleanupProduct(product1Id) : Promise.resolve(),
                product2Id ? cleanupProduct(product2Id) : Promise.resolve(),
                categoryId ? cleanupCategory(categoryId) : Promise.resolve()
            ]);
            console.log('🧹 Cleanup completed');
        }
    });

    test('Delete category and verify removal from Facebook catalog', async ({ page }, testInfo) => {
        let productId = null;
        let categoryId = null;

        try {
            // Step 1: Create a test category via API
            console.log('📁 Step 1: Creating test category via API...');
            const categoryData = await createTestCategory({
                description: 'Test category for deletion testing'
            });
            categoryId = categoryData.categoryId;
            const categoryName = categoryData.categoryName;

            // Step 2: Create a product and attach it to the category via API
            console.log('📦 Step 2: Creating product and attaching to category via API...');
            const product = await createTestProduct({
                productType: 'simple',
                price: '24.99',
                stock: '15',
                categoryIds: [categoryId]
            });
            productId = product.productId;
            console.log(`✅ Created product: ${product.productName} (ID: ${productId}) with category ${categoryId}`);

            // Step 3: Perform initial validation
            console.log('🔍 Step 3: Performing initial validation...');
            const initialCategoryResult = await validateCategorySync(categoryId, categoryName, 5);
            expect(initialCategoryResult['success']).toBe(true);
            console.log('✅ Initial category sync validated');

            const facebookProductSetId = initialCategoryResult['facebook_product_set_id'];
            console.log(`📊 Facebook Product Set ID: ${facebookProductSetId}`);

            // Validate product is in the category
            const initialProductResult = await validateFacebookSync(productId, product.productName, 5);
            expect(initialProductResult['success']).toBe(true);
            const isProductInInitialSet = initialProductResult['raw_data']['facebook_data'][0]['product_sets'].some(
                set => Number(categoryId) === Number(set.retailer_id)
            );
            expect(isProductInInitialSet).toBe(true);
            console.log('✅ Product validated in category product set');

            // Step 4: Delete the test category via UI
            console.log('🗑️ Step 4: Deleting category via UI...');

            // Navigate to Categories page
            await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });
            console.log('✅ Navigated to Categories page');

            // Find the category row
            const categoryRow = page.locator(`tr#tag-${categoryId}`);
            await categoryRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

            // Hover over the category row to reveal the delete link
            await categoryRow.hover();

            // Click the Delete link
            const deleteLink = categoryRow.locator('a.delete-tag');
            await deleteLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

            // Set up dialog handler to confirm deletion
            page.on('dialog', async dialog => {
                console.log(`📝 Dialog message: ${dialog.message()}`);
                await dialog.accept();
                console.log('✅ Confirmed deletion dialog');
            });

            await deleteLink.click();
            await page.waitForLoadState('domcontentloaded');
            console.log(`✅ Deleted category ${categoryId} from UI`);

            // Step 5: Validate the category no longer exists in Facebook catalog
            console.log('🔍 Step 5: Validating category removal from Facebook...');
            const deletedCategoryResult = await validateCategorySync(categoryId, categoryName, 5);
            expect(deletedCategoryResult['success']).toBe(false);
            console.log('✅ Verified category no longer syncs to Facebook');

            // Step 6: Check the product no longer belongs to the category/product set
            console.log('🔍 Step 6: Verifying product no longer belongs to the category...');
            const finalProductResult = await validateFacebookSync(productId, product.productName, 5);
            expect(finalProductResult['success']).toBe(true);

            // Check if product still has the deleted category in its product sets
            const isProductStillInSet = finalProductResult['raw_data']['facebook_data'][0]['product_sets'].some(
                set => Number(categoryId) === Number(set.retailer_id)
            );
            expect(isProductStillInSet).toBe(false);
            console.log('✅ Verified product no longer belongs to the deleted category');

            // Verify in the UI that the category is no longer assigned to the product
            await page.goto(`${baseURL}/wp-admin/post.php?post=${productId}&action=edit`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });

            const categoryCheckbox = page.getByRole('checkbox', { name: categoryName });
            const categoryExists = await categoryCheckbox.isVisible({ timeout: TIMEOUTS.MEDIUM }).catch(() => false);
            expect(categoryExists).toBe(false);
            console.log('✅ Verified category no longer appears in product edit UI');

            console.log('✅ Category deletion test completed successfully');
            logTestEnd(testInfo, true);

        } catch (error) {
            console.log(`⚠️ Category deletion test failed: ${error.message}`);
            await safeScreenshot(page, 'category-deletion-test-failure.png');
            logTestEnd(testInfo, false);
            throw error;
        } finally {
            // Cleanup product (category already deleted in the test)
            await Promise.all([
                productId ? cleanupProduct(productId) : Promise.resolve()
            ]);
            console.log('🧹 Cleanup completed');
        }
    });

});
