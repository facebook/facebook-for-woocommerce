const { test, expect } = require('@playwright/test');
const {
  TIMEOUTS,
  baseURL,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  publishProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  setProductTitle,
  setProductDescription,
} = require('./helpers/js');

test.describe.serial('Variable Product Depth Tests', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium-wp-admin', 'Variable product depth tests require wp-admin project');

    logTestStart(testInfo);
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  const VARIABLE_PRODUCT_CONFIG = {
    attributes: {
      Color: ['Red', 'Blue'],
      Size: ['Small', 'Medium'],
      Material: ['Cotton', 'Wool'],
    },
    basePrice: 41,
    baseStock: 5,
    syncWaitSeconds: 5,
    syncMaxRetries: 8,
  };

  function buildPipeList(values) {
    return values.join('|');
  }

  function getVariationCount(attributes) {
    return Object.values(attributes).reduce((total, values) => total * values.length, 1);
  }

  function buildVariationDefinitions(expectedCount) {
    return Array.from({ length: expectedCount }, (_, index) => ({
      index,
      price: (VARIABLE_PRODUCT_CONFIG.basePrice + index).toFixed(2),
      sku: generateUniqueSKU(`var-depth-${index + 1}`),
      stock: String(VARIABLE_PRODUCT_CONFIG.baseStock + index),
    }));
  }

  function normalizeText(value) {
    return String(value || '').trim().toLowerCase();
  }

  function normalizePrice(value) {
    const match = String(value || '').match(/\d+(?:\.\d+)?/);
    return match ? Number(match[0]).toFixed(2) : null;
  }

  async function resolveProductId(page) {
    const urlProductId = extractProductIdFromUrl(page.url());
    if (urlProductId) {
      return urlProductId;
    }

    const postIdField = page.locator('#post_ID, input[name="post_ID"]').first();
    if (await postIdField.count()) {
      const value = await postIdField.getAttribute('value');
      if (value && /^\d+$/.test(value)) {
        return Number(value);
      }
    }

    throw new Error(`Unable to resolve product ID from URL or #post_ID. Current URL: ${page.url()}`);
  }

  async function waitForProductEditor(page) {
    await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
    await page.locator('#title').waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG });
    await page.locator('#woocommerce-product-data').waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG });
  }

  async function openVariationsTab(page) {
    const variationsTab = page.locator('li.variations_tab a, a[href="#variable_product_options"]').first();
    await variationsTab.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await variationsTab.click();
    await page.locator('#variable_product_options').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  }

  async function expandAllVariations(page) {
    const expandAllButton = page.getByRole('link', { name: 'Expand' }).first();
    await expandAllButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await expandAllButton.click();
    await page.waitForTimeout(TIMEOUTS.NORMAL);
  }

  async function waitForVariationCount(page, expectedCount) {
    await page.waitForFunction(
      (count) => document.querySelectorAll('.woocommerce_variation').length === count,
      expectedCount,
      { timeout: TIMEOUTS.EXTRA_LONG }
    );
  }

  async function saveVariationChanges(page) {
    const saveVariationsButton = page.locator('button.save-variation-changes');
    await saveVariationsButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    if (await saveVariationsButton.isEnabled()) {
      await saveVariationsButton.click();
      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX }).catch(() => {});
      await page.waitForTimeout(TIMEOUTS.NORMAL);
      console.log('✅ Saved variation changes');
    } else {
      console.log('ℹ️ Variation changes button already disabled; nothing to save');
    }
  }

  async function setupAttributes(page, attributes) {
    await page.click('li.attribute_tab a[href="#product_attributes"]');

    const attributeEntries = Object.entries(attributes);
    const addCustomAttributeButton = page.locator('button.add_custom_attribute').first();
    await addCustomAttributeButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    for (let i = 0; i < attributeEntries.length; i++) {
      const [attributeName, values] = attributeEntries[i];
      const nameSelector = `input.attribute_name[name="attribute_names[${i}]"]`;
      const valuesSelector = `textarea[name="attribute_values[${i}]"]`;

      if (i > 0) {
        await addCustomAttributeButton.click();
      }

      await page.locator(nameSelector).waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG });
      await page.fill(nameSelector, attributeName);
      await page.fill(valuesSelector, buildPipeList(values));

      const attributeRow = page.locator('#product_attributes .woocommerce_attribute').nth(i);
      const usedForVariationsCheckbox = attributeRow.locator('input.woocommerce_attribute_used_for_variations, input[name^="attribute_variation["]').first();
      if (await usedForVariationsCheckbox.count()) {
        const isChecked = await usedForVariationsCheckbox.isChecked().catch(() => false);
        if (!isChecked) {
          await usedForVariationsCheckbox.check({ force: true });
        }
      }

      console.log(`✅ Configured attribute ${attributeName}: ${values.join(', ')}`);
    }

    await page.locator('#product_attributes .woocommerce_attribute textarea[name^="attribute_values"]').last().press('Tab');
    await page.click('button.save_attributes.button-primary');

    await page.waitForFunction((expectedCount) => {
      return document.querySelectorAll('.woocommerce_attribute.wc-metabox.postbox.closed').length >= expectedCount;
    }, attributeEntries.length, { timeout: TIMEOUTS.EXTRA_LONG });

    console.log('✅ Saved variable product attributes');
  }

  async function generateAllVariations(page, expectedCount) {
    await openVariationsTab(page);

    page.once('dialog', async dialog => {
      console.log(`📢 Variation generation dialog: ${dialog.message()}`);
      await dialog.accept();
    });

    const generateVariationsButton = page.locator('button.generate_variations');
    await generateVariationsButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await generateVariationsButton.click();

    await waitForVariationCount(page, expectedCount);
    const actualCount = await page.locator('.woocommerce_variation').count();
    expect(actualCount).toBe(expectedCount);
    console.log(`✅ Generated ${actualCount} variations`);
  }

  async function assignVariationData(page, variationDefinitions) {
    await openVariationsTab(page);
    await expandAllVariations(page);

    const variationRows = page.locator('.woocommerce_variation');
    await expect(variationRows).toHaveCount(variationDefinitions.length);

    for (let i = 0; i < variationDefinitions.length; i++) {
      const variationRow = variationRows.nth(i);
      const variationData = variationDefinitions[i];

      await variationRow.scrollIntoViewIfNeeded();

      const priceField = variationRow.locator('input[name*="variable_regular_price"]').first();
      await priceField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await priceField.fill(variationData.price);

      const skuField = variationRow.locator('input[name*="variable_sku"]').first();
      await skuField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await skuField.fill(variationData.sku);

      const manageStockCheckbox = variationRow.locator('input.variable_manage_stock, input[name*="variable_manage_stock"]').first();
      if (await manageStockCheckbox.count()) {
        const isChecked = await manageStockCheckbox.isChecked().catch(() => false);
        if (!isChecked) {
          await manageStockCheckbox.check({ force: true });
        }
      }

      const stockField = variationRow.locator('input[name*="variable_stock"]').first();
      await stockField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await stockField.fill(variationData.stock);

      console.log(`✅ Variation ${i + 1}: price=${variationData.price}, sku=${variationData.sku}, stock=${variationData.stock}`);
    }

    await saveVariationChanges(page);
  }

  async function verifyVariationInputs(page, variationDefinitions, { expectedPriceForAll = null } = {}) {
    await openVariationsTab(page);
    await expandAllVariations(page);

    const variationRows = page.locator('.woocommerce_variation');
    await expect(variationRows).toHaveCount(variationDefinitions.length);

    for (let i = 0; i < variationDefinitions.length; i++) {
      const variationRow = variationRows.nth(i);
      const definition = variationDefinitions[i];

      const priceField = variationRow.locator('input[name*="variable_regular_price"]').first();
      const skuField = variationRow.locator('input[name*="variable_sku"]').first();
      const stockField = variationRow.locator('input[name*="variable_stock"]').first();

      await priceField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await skuField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await stockField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

      const actualPrice = await priceField.inputValue();
      const actualSku = await skuField.inputValue();
      const actualStock = await stockField.inputValue();

      expect(actualPrice).toBe(expectedPriceForAll ?? definition.price);
      expect(actualSku).toBe(definition.sku);

      if (!expectedPriceForAll) {
        expect(actualStock).toBe(definition.stock);
      }
    }
  }

  async function validateVariableProductSync(productId, productName) {
    const result = await validateFacebookSync(
      productId,
      productName,
      VARIABLE_PRODUCT_CONFIG.syncWaitSeconds,
      VARIABLE_PRODUCT_CONFIG.syncMaxRetries
    );

    expect(result).not.toBeNull();
    expect(result.success).toBe(true);
    return result;
  }

  function assertVariationAttributeMapping(syncResult, expectedCount) {
    const wooData = syncResult.raw_data?.woo_data || [];
    const facebookData = syncResult.raw_data?.facebook_data || [];

    expect(wooData.length).toBe(expectedCount);
    expect(facebookData.length).toBe(expectedCount);

    const facebookByRetailerId = new Map(
      facebookData
        .filter(item => item && item.found !== false)
        .map(item => [item.retailer_id, item])
    );

    for (const wooItem of wooData) {
      const facebookItem = facebookByRetailerId.get(wooItem.retailer_id);
      expect(facebookItem, `Missing Facebook data for retailer_id ${wooItem.retailer_id}`).toBeTruthy();
      expect(normalizeText(facebookItem.color)).toBe(normalizeText(wooItem.color));
      expect(normalizeText(facebookItem.size)).toBe(normalizeText(wooItem.size));
    }

    console.log(`✅ Variation-level color/size mapping preserved for ${expectedCount} synced variations`);
  }

  function assertExpectedVariationPricesInSync(syncResult, expectedPrices) {
    const wooPrices = (syncResult.raw_data?.woo_data || []).map(item => normalizePrice(item.price)).sort();
    const expectedNormalizedPrices = expectedPrices.map(price => normalizePrice(price)).sort();
    expect(wooPrices).toEqual(expectedNormalizedPrices);
  }

  function mapWooDataByRetailerId(syncResult) {
    return new Map((syncResult.raw_data?.woo_data || []).map(item => [item.retailer_id, item]));
  }

  async function createPublishedVariableProduct(page) {
    const productName = generateProductName('VariableDepth');
    const expectedVariationCount = getVariationCount(VARIABLE_PRODUCT_CONFIG.attributes);
    const variationDefinitions = buildVariationDefinitions(expectedVariationCount);

    await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX,
    });

    await waitForProductEditor(page);
    await setProductTitle(page, productName);
    await setProductDescription(page, 'Advanced variable product depth test with multiple attributes and variation-level updates.');

    await page.selectOption('#product-type', 'variable');
    await page.locator('#_sku').fill(generateUniqueSKU('variable-depth-parent'));

    await setupAttributes(page, VARIABLE_PRODUCT_CONFIG.attributes);
    await generateAllVariations(page, expectedVariationCount);
    await assignVariationData(page, variationDefinitions);

    await publishProduct(page);

    const productId = await resolveProductId(page);
    await checkForPhpErrors(page);

    const syncResult = await validateVariableProductSync(productId, productName);
    assertVariationAttributeMapping(syncResult, expectedVariationCount);
    assertExpectedVariationPricesInSync(syncResult, variationDefinitions.map(item => item.price));

    return {
      productId,
      productName,
      expectedVariationCount,
      variationDefinitions,
      syncResult,
    };
  }

  async function goToProductEditPage(page, productId) {
    await page.goto(`${baseURL}/wp-admin/post.php?post=${productId}&action=edit`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX,
    });
    await waitForProductEditor(page);
  }

  async function bulkSetRegularPrice(page, newPrice) {
    await openVariationsTab(page);
    await expandAllVariations(page);

    page.once('dialog', async dialog => {
      console.log(`📢 Bulk edit dialog: ${dialog.message()}`);
      await dialog.accept(newPrice);
    });

    const bulkActionsSelect = page.locator('select.variation_actions, #field_to_edit').first();
    await bulkActionsSelect.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await bulkActionsSelect.selectOption('variable_regular_price');
    await page.waitForTimeout(TIMEOUTS.NORMAL);

    await saveVariationChanges(page);
    console.log(`✅ Bulk set variation regular price to ${newPrice}`);
  }

  async function editSingleVariation(page, variationIndex, newPrice, newStock) {
    await openVariationsTab(page);
    await expandAllVariations(page);

    const variationRow = page.locator('.woocommerce_variation').nth(variationIndex);
    await variationRow.scrollIntoViewIfNeeded();

    const priceField = variationRow.locator('input[name*="variable_regular_price"]').first();
    await priceField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await priceField.fill(newPrice);

    const manageStockCheckbox = variationRow.locator('input.variable_manage_stock, input[name*="variable_manage_stock"]').first();
    if (await manageStockCheckbox.count()) {
      const isChecked = await manageStockCheckbox.isChecked().catch(() => false);
      if (!isChecked) {
        await manageStockCheckbox.check({ force: true });
      }
    }

    const stockField = variationRow.locator('input[name*="variable_stock"]').first();
    await stockField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await stockField.fill(newStock);

    await saveVariationChanges(page);
    console.log(`✅ Edited variation ${variationIndex + 1}: new price=${newPrice}, new stock=${newStock}`);
  }

  async function deleteVariation(page, variationIndex, expectedCountAfterDelete) {
    await openVariationsTab(page);
    await expandAllVariations(page);

    const variationRow = page.locator('.woocommerce_variation').nth(variationIndex);
    await variationRow.scrollIntoViewIfNeeded();

    page.once('dialog', async dialog => {
      console.log(`📢 Delete variation dialog: ${dialog.message()}`);
      await dialog.accept();
    });

    const removeVariationLink = variationRow.locator('a.remove_variation').first();
    await removeVariationLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await removeVariationLink.click();

    await waitForVariationCount(page, expectedCountAfterDelete);
    console.log(`✅ Deleted one variation, ${expectedCountAfterDelete} variations remain in UI`);
  }

  async function regenerateAllVariations(page, expectedCount) {
    await openVariationsTab(page);

    page.once('dialog', async dialog => {
      console.log(`📢 Regenerate variations dialog: ${dialog.message()}`);
      await dialog.accept();
    });

    const generateVariationsButton = page.locator('button.generate_variations');
    await generateVariationsButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await generateVariationsButton.click();

    await waitForVariationCount(page, expectedCount);
    console.log(`✅ Regenerated variations back to ${expectedCount}`);
  }

  test('creates a variable product with multiple attributes and preserves variation-level mapping after sync', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const created = await createPublishedVariableProduct(page);
      productId = created.productId;

      await goToProductEditPage(page, productId);
      await verifyVariationInputs(page, created.variationDefinitions);

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'variable-product-depth-create-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('syncs changes after editing only one variation', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const created = await createPublishedVariableProduct(page);
      productId = created.productId;

      const beforeMap = mapWooDataByRetailerId(created.syncResult);
      const originalTarget = created.syncResult.raw_data.woo_data.find(
        item => normalizePrice(item.price) === created.variationDefinitions[0].price
      );

      expect(originalTarget).toBeTruthy();

      const updatedPrice = '88.88';
      const updatedStock = '42';

      await goToProductEditPage(page, productId);
      await editSingleVariation(page, 0, updatedPrice, updatedStock);
      await publishProduct(page);
      await checkForPhpErrors(page);

      const syncResultAfterEdit = await validateVariableProductSync(productId, created.productName);
      assertVariationAttributeMapping(syncResultAfterEdit, created.expectedVariationCount);

      const afterMap = mapWooDataByRetailerId(syncResultAfterEdit);
      expect(afterMap.get(originalTarget.retailer_id)).toBeTruthy();
      expect(normalizePrice(afterMap.get(originalTarget.retailer_id).price)).toBe(updatedPrice);

      for (const [retailerId, beforeItem] of beforeMap.entries()) {
        const afterItem = afterMap.get(retailerId);
        expect(afterItem).toBeTruthy();

        if (retailerId === originalTarget.retailer_id) {
          continue;
        }

        expect(normalizePrice(afterItem.price)).toBe(normalizePrice(beforeItem.price));
      }

      await goToProductEditPage(page, productId);
      await openVariationsTab(page);
      await expandAllVariations(page);
      const firstVariationRow = page.locator('.woocommerce_variation').first();
      await expect(firstVariationRow.locator('input[name*="variable_regular_price"]').first()).toHaveValue(updatedPrice);
      await expect(firstVariationRow.locator('input[name*="variable_stock"]').first()).toHaveValue(updatedStock);

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'variable-product-depth-single-edit-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('applies native WooCommerce bulk price edits to every variation without skipping any', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const created = await createPublishedVariableProduct(page);
      productId = created.productId;

      const bulkPrice = '99.99';

      await goToProductEditPage(page, productId);
      await bulkSetRegularPrice(page, bulkPrice);
      await publishProduct(page);
      await checkForPhpErrors(page);

      await goToProductEditPage(page, productId);
      await verifyVariationInputs(page, created.variationDefinitions, { expectedPriceForAll: bulkPrice });

      const syncResultAfterBulkEdit = await validateVariableProductSync(productId, created.productName);
      assertVariationAttributeMapping(syncResultAfterBulkEdit, created.expectedVariationCount);

      const wooPrices = (syncResultAfterBulkEdit.raw_data.woo_data || []).map(item => normalizePrice(item.price));
      const facebookPrices = (syncResultAfterBulkEdit.raw_data.facebook_data || []).map(item => normalizePrice(item.price));

      expect(new Set(wooPrices)).toEqual(new Set([bulkPrice]));
      expect(new Set(facebookPrices)).toEqual(new Set([bulkPrice]));

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'variable-product-depth-bulk-edit-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('syncs deletion and regeneration of variations after the initial sync', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const created = await createPublishedVariableProduct(page);
      productId = created.productId;

      await goToProductEditPage(page, productId);
      await deleteVariation(page, 0, created.expectedVariationCount - 1);
      await saveVariationChanges(page);
      await publishProduct(page);
      await checkForPhpErrors(page);

      const syncResultAfterDelete = await validateVariableProductSync(productId, created.productName);
      assertVariationAttributeMapping(syncResultAfterDelete, created.expectedVariationCount - 1);
      expect(syncResultAfterDelete.raw_data.woo_data).toHaveLength(created.expectedVariationCount - 1);

      await goToProductEditPage(page, productId);
      await regenerateAllVariations(page, created.expectedVariationCount);
      await bulkSetRegularPrice(page, '77.77');
      await publishProduct(page);
      await checkForPhpErrors(page);

      const syncResultAfterRegeneration = await validateVariableProductSync(productId, created.productName);
      assertVariationAttributeMapping(syncResultAfterRegeneration, created.expectedVariationCount);
      expect(syncResultAfterRegeneration.raw_data.woo_data).toHaveLength(created.expectedVariationCount);

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'variable-product-depth-delete-regenerate-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });
});
