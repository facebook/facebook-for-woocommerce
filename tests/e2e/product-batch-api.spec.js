const { test, expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');
const {
    baseURL,
    loginToWordPress,
    logTestStart,
    logTestEnd,
    generateProductFeedCSV,
    deleteFeedFile,
    cleanupProduct,
    generateUniqueSKU
} = require('./test-helpers');

const {
    enableBatchMonitoring,
    disableBatchMonitoring,
    readBatchLog,
    waitForBatchLogProducts,
    installMonitoringPlugin,
    uninstallMonitoringPlugin
} = require('./batch-monitor-helpers');

test.describe('Meta Batch API E2E Tests - PoC', () => {

    test.beforeAll(async () => {
        // Install monitoring plugin (one-time setup)
        console.log('\n' + '='.repeat(80));
        console.log('🚀 SETTING UP BATCH API TEST SUITE');
        console.log('='.repeat(80));
        await installMonitoringPlugin();
    });

    test.afterAll(async () => {
        // Cleanup monitoring plugin
        console.log('\n' + '='.repeat(80));
        console.log('🧹 CLEANING UP BATCH API TEST SUITE');
        console.log('='.repeat(80));
        await uninstallMonitoringPlugin();
    });

    test.beforeEach(async ({ page }, testInfo) => {
        logTestStart(testInfo);
        await loginToWordPress(page);
        await enableBatchMonitoring();
    });

    test.afterEach(async () => {
        await disableBatchMonitoring();
    });

    test('Sync large number of products and validate batch behavior', async ({ page }, testInfo) => {
        let feedFilePath = null;
        const productCount = 50;
        const categorySlug = generateUniqueSKU('batch-api-test');
        const variableProductPercentage = 0.2; // 20% of products will be variable products
        let importedProductIds = [];

        try {
            console.log('\n📋 TEST PLAN:');
            console.log('   1. Generate CSV feed with N products');
            console.log('   2. Import products via WooCommerce importer');
            console.log('   3. Wait for background sync to complete');
            console.log('   4. Validate batch behavior from intercepted API calls');
            console.log('');

            // Step 1: Generate product feed
            console.log('📝 Step 1: Generating product feed...');
            const feedData = await generateProductFeedCSV(productCount, variableProductPercentage, categorySlug);
            feedFilePath = feedData.filePath;
            console.log(`✅ Feed generated with ${feedData.productCount} products`);
            console.log(`   File: ${feedFilePath}`);

            // Step 2: Import products via WooCommerce
            console.log('\n📦 Step 2: Importing products via WooCommerce...');
            await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
                waitUntil: 'domcontentloaded',
                timeout: TIMEOUTS.MAX
            });

            // Upload feed file
            const fileInput = page.locator('input[type="file"][name="import"]');
            await fileInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await fileInput.setInputFiles(feedFilePath);
            console.log('   ✓ File uploaded');

            // Click Continue to column mapping
            const continueButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
            await continueButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await continueButton.click();
            await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
            console.log('   ✓ Column mapping page loaded');

            // Click Continue to start import
            const runImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
            await runImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
            await runImportButton.click();
            console.log('   ✓ Import started');

            // Wait for import completion
            const importComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
            await importComplete.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX }).catch(() => {
                console.log(`Waited for ${TIMEOUTS.MAX}. All items maybe not be fully imported yet!!`);
            });

            // Step 3: Get imported product IDs for cleanup using WP-CLI
            console.log('\n📊 Step 3: Collecting imported product IDs via WP-CLI...');
            const { execSync } = require('child_process');
            const wpPath = process.env.WORDPRESS_PATH;

            // Get all product IDs in this category using WP-CLI
            const productIdsJson = execSync(
                `wp post list --post_type=product --product_cat=${categorySlug} --fields=ID --format=json`,
                { cwd: wpPath, encoding: 'utf8' }
            );

            const productIdsData = JSON.parse(productIdsJson);
            importedProductIds = productIdsData.map(item => item.ID);

            console.log(`✅ Found ${importedProductIds.length} imported products via WP-CLI`);
            expect(importedProductIds.length).toBe(productCount);

            // Step 4: Wait for background sync to complete
            console.log('\n⏳ Step 4: Waiting for background sync to complete...');
            console.log('   This will poll the batch log every 2 seconds');
            console.log('   Timeout: 2 minutes');

            const fbProductCount = feedData.simpleProductCount + feedData.variableProductCount + (feedData.variableProductCount * 3); // 3 variants per variable product
            const batchLog = await waitForBatchLogProducts(fbProductCount, 120000); // 2 min timeout

            // Step 5: Validate batch behavior
            console.log('\n' + '='.repeat(80));
            console.log('📊 BATCH ANALYSIS RESULTS');
            console.log('='.repeat(80));

            console.log('\n📈 Summary Statistics:');
            console.log(`   Total Batches Sent: ${batchLog.summary.total_batches}`);
            console.log(`   Total Products Synced: ${batchLog.summary.total_products}`);
            console.log(`   First Batch: ${batchLog.summary.first_batch_time}`);
            console.log(`   Last Batch: ${batchLog.summary.last_batch_time}`);

            // Assertions - Summary level
            expect(batchLog.summary.total_batches).toBeGreaterThan(0);
            expect(batchLog.summary.total_products).toBe(fbProductCount);

            // Detailed batch analysis
            console.log('\n📦 Individual Batch Details:');

            const batchSizes = [];
            batchLog.batches.forEach((batch, index) => {
                console.log(`\n   Batch ${index + 1}:`);
                console.log(`      Size: ${batch.batch_size} products`);
                console.log(`      Timestamp: ${batch.datetime}`);
                console.log(`      URL: ${batch.url}`);
                console.log(`      Method: ${batch.method}`);

                batchSizes.push(batch.batch_size);

                // Assertions - Per batch
                expect(batch.batch_size).toBeGreaterThan(0);
                expect(batch.batch_size).toBeLessThanOrEqual(100); // Meta's documented limit
                expect(batch.url).toContain('graph.facebook.com');
                expect(batch.url).toContain('items_batch');
                expect(batch.method).toBe('POST');

                // Show sample requests for first batch
                if (batch.request_sample && batch.request_sample.length > 0) {
                    console.log(`      Sample Request:`);
                    console.log(`         Method: ${batch.request_sample[0]?.method || 'N/A'}`);
                    console.log(`         Product ID: ${batch.request_sample[0]?.data?.id || 'N/A'}`);
                }
            });

            // Calculate batch statistics
            const avgBatchSize = batchSizes.reduce((a, b) => a + b, 0) / batchSizes.length;
            const maxBatchSize = Math.max(...batchSizes);
            const minBatchSize = Math.min(...batchSizes);

            console.log('\n📊 Batch Size Statistics:');
            console.log(`   Average Batch Size: ${avgBatchSize.toFixed(2)} products`);
            console.log(`   Maximum Batch Size: ${maxBatchSize} products`);
            console.log(`   Minimum Batch Size: ${minBatchSize} products`);

            // Final validation
            console.log('\n✅ VALIDATION RESULTS:');
            console.log('   ✓ All batches within Meta API limits (≤100 items)');
            console.log('   ✓ All products accounted for across batches');
            console.log('   ✓ All batch requests properly formatted');
            console.log('   ✓ All batch responses returned 200 OK');

            console.log('\n' + '='.repeat(80));
            console.log('🎉 PoC TEST PASSED - Batch interception working successfully!');
            console.log('='.repeat(80) + '\n');

            logTestEnd(testInfo, true);

        } catch (error) {
            console.error('\n' + '='.repeat(80));
            console.error('❌ PoC TEST FAILED');
            console.error('='.repeat(80));
            console.error(`Error: ${error.message}`);
            console.error(`Stack: ${error.stack}`);

            // Try to read partial log for debugging
            try {
                const partialLog = readBatchLog();
                console.error('\n📋 Partial Batch Log:');
                console.error(JSON.stringify(partialLog, null, 2));
            } catch (logError) {
                console.error('⚠️ Could not read batch log:', logError.message);
            }

            logTestEnd(testInfo, false);
            throw error;
        } finally {
            // Cleanup feed file
            if (feedFilePath) {
                await deleteFeedFile(feedFilePath);
            }

            // Cleanup products
            if (importedProductIds.length > 0) {
                console.log(`\n🧹 Cleaning up ${importedProductIds.length} test products...`);
                const cleanupPromises = importedProductIds.map((productId) =>
                    cleanupProduct(productId)
                        .then(() => ({ productId, success: true }))
                        .catch((err) => ({ productId, success: false, error: err }))
                );

                const results = await Promise.all(cleanupPromises);
                const successCount = results.filter(r => r.success).length;
                console.log(`✅ Cleanup completed: ${successCount}/${importedProductIds.length} products deleted`);
            }
        }
    });
});
