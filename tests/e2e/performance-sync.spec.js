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
  createTestProduct,
  exactSearchSelect2Container
} = require('./helpers/js');

test.describe.serial('Meta for WooCommerce - Performance Sync E2E Tests', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    logTestStart(testInfo);
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  const PERFORMANCE_CONFIG = {
    simpleBatch: {
      count: 15,
      descriptionRepeat: 15,
      price: '19.99',
      waitSeconds: 5,
    },
    longContent: {
      descriptionRepeat: 60,
      price: '39.99',
      attributes: {
        Color: ['Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange'],
        Size: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
      },
      waitSeconds: 5,
      maxRetries: 8,
    },
    manyVariations: {
      attributeName: 'Material',
      attributeValues: ['Cotton', 'Wool', 'Linen', 'Polyester', 'Silk', 'Denim', 'Leather', 'Nylon'],
      price: '29.99',
      waitSeconds: 5,
      maxRetries: 8,
    },
    repeatedUpdates: {
      updates: 3,
      initialPrice: '11.00',
      priceStep: 1,
      waitSeconds: 5,
    },
    concurrent: {
      price: '25.00',
      waitSeconds: 5,
    },
  };

  function buildLongText(prefix, repeatCount) {
    return Array.from({ length: repeatCount }, (_, i) => `${prefix} block ${i + 1}`).join(' ');
  }

  function countVariationsFromAttributes(attributes) {
    return Object.values(attributes).reduce((total, values) => total * values.length, 1);
  }

  function buildPipeList(values) {
    return values.join('|');
  }

  async function waitForProductEditorReady(page, targetUrl = null) {
    for (let attempt = 1; attempt <= 3; attempt++) {
      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX }).catch(() => {});

      const currentUrl = page.url();
      const isLoginPage = /\/wp-login\.php/.test(currentUrl)
        || await page.locator('#loginform').first().isVisible().catch(() => false);

      if (isLoginPage) {
        console.warn(`⚠️ Landed on login page while waiting for product editor (attempt ${attempt}). URL: ${currentUrl}`);
        await loginToWordPress(page);

        if (targetUrl) {
          await page.goto(targetUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.MAX,
          });
        }
      }

      const editorReady = await page.waitForFunction(() => {
        const titleField = document.querySelector('#title');
        const postStuff = document.querySelector('#poststuff');
        const productData = document.querySelector('#woocommerce-product-data');
        const isVisible = (el) => !!el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);

        return isVisible(titleField) || (isVisible(postStuff) && !!titleField) || (isVisible(productData) && !!titleField);
      }, { timeout: TIMEOUTS.EXTRA_LONG }).then(() => true).catch(() => false);

      if (editorReady) {
        await page.locator('#title').waitFor({
          state: 'visible',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        return;
      }

      const pageTitle = await page.title().catch(() => 'unknown');
      console.warn(`⚠️ Product editor not ready on attempt ${attempt}. URL: ${page.url()} | title: ${pageTitle}`);

      if (attempt < 3) {
        if (targetUrl) {
          await page.goto(targetUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.MAX,
          });
        } else {
          await page.reload({
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.MAX,
          });
        }
      }
    }

    throw new Error(`Product editor did not become ready after retries. Final URL: ${page.url()}`);
  }

  async function resolveProductId(page) {
    const getProductIdFromPage = async () => {
      const urlProductId = extractProductIdFromUrl(page.url());
      if (urlProductId) {
        return urlProductId;
      }

      const postIdField = page.locator('#post_ID, input[name="post_ID"]').first();
      if (await postIdField.count()) {
        const value = await postIdField.getAttribute('value');
        const fieldProductId = value && /^\d+$/.test(value) ? Number(value) : null;
        if (fieldProductId) {
          console.log(`✅ Resolved Product ID from #post_ID field: ${fieldProductId}`);
          return fieldProductId;
        }
      }

      return null;
    };

    let productId = await getProductIdFromPage();
    if (productId) {
      return productId;
    }

    await page.waitForFunction(() => {
      const url = new URL(window.location.href);
      const urlProductId = url.searchParams.get('post');
      if (urlProductId && /^\d+$/.test(urlProductId)) {
        return true;
      }

      const postIdField = document.querySelector('#post_ID, input[name="post_ID"]');
      const fieldValue = postIdField?.value || postIdField?.getAttribute('value') || '';
      return /^\d+$/.test(fieldValue);
    }, { timeout: TIMEOUTS.EXTRA_LONG });

    productId = await getProductIdFromPage();

    if (!productId) {
      throw new Error(`Unable to resolve product ID after publish. Current URL: ${page.url()}`);
    }

    return productId;
  }

  async function assertNoFatalAndSync(page, productId, productName, waitSeconds = 5, maxRetries) {
    const resolvedProductId = productId || await resolveProductId(page);

    await checkForPhpErrors(page);

    console.log(`🔍 Running Facebook sync validation using product-creation.spec.js logic for product ${resolvedProductId}`);
    console.log(`   waitSeconds: ${waitSeconds}${maxRetries ? ` | maxRetries: ${maxRetries}` : ''}`);
    console.log(`   current page URL: ${page.url()}`);

    const result = maxRetries == null
      ? await validateFacebookSync(resolvedProductId, productName, waitSeconds)
      : await validateFacebookSync(resolvedProductId, productName, waitSeconds, maxRetries);

    expect(result).not.toBeNull();

    if (!result) {
      throw new Error(`Facebook sync returned null for product ${resolvedProductId} (likely HTML response instead of JSON)`);
    }

    expect(result.success).toBe(true);
    return result;
  }

  async function createAndPublishSimpleProduct(page, { titleSuffix, descriptionRepeat = 20 }) {
    let productId = null;

    try {
      const newProductUrl = `${baseURL}/wp-admin/post-new.php?post_type=product`;
      await page.goto(newProductUrl, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX,
      });
      await waitForProductEditorReady(page, newProductUrl);

      const productName = generateProductName(titleSuffix);
      await setProductTitle(page, productName);
      await setProductDescription(
        page,
        buildLongText(`Long description for ${productName}.`, descriptionRepeat)
      );

      await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

      await page.click('li.general_tab a');
      await page.locator('#_regular_price').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await page.locator('#_regular_price').fill(PERFORMANCE_CONFIG.simpleBatch.price);

      await page.click('li.inventory_tab a');
      const sku = generateUniqueSKU(titleSuffix.toLowerCase());
      await page.locator('#_sku').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await page.locator('#_sku').fill(sku);

      await publishProduct(page);

      productId = await resolveProductId(page);
      await assertNoFatalAndSync(
        page,
        productId,
        productName,
        PERFORMANCE_CONFIG.simpleBatch.waitSeconds
      );

      return { productId, productName, sku };
    } catch (error) {
      await safeScreenshot(page, `simple-product-stress-failure-${titleSuffix}.png`);
      throw error;
    }
  }

  async function setupVariableProduct(page, { productName, description, skuPrefix, attributes }) {
    const newProductUrl = `${baseURL}/wp-admin/post-new.php?post_type=product`;
    await page.goto(newProductUrl, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX,
    });
    await waitForProductEditorReady(page, newProductUrl);

    await setProductTitle(page, productName);
    await setProductDescription(page, description);
    await page.selectOption('#product-type', 'variable');
    await page.locator('#_sku').fill(generateUniqueSKU(skuPrefix));

    await page.click('li.attribute_tab a[href="#product_attributes"]');
    const attributeEntries = Object.entries(attributes);

    for (let i = 0; i < attributeEntries.length; i++) {
      const [attributeName, values] = attributeEntries[i];
      const nameSelector = `input.attribute_name[name="attribute_names[${i}]"]`;
      const valuesSelector = `textarea[name="attribute_values[${i}]"]`;

      await page.locator(nameSelector).waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await page.fill(nameSelector, attributeName);
      await page.fill(valuesSelector, buildPipeList(values));
    }

    await page.locator('#product_attributes .woocommerce_attribute textarea[name^="attribute_values"]').first().press('Tab');
    await page.click('button.save_attributes.button-primary');

    await page.click('a[href="#variable_product_options"]');
    await page.locator('button.generate_variations').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await page.click('button.generate_variations');

    return { productName };
  }

  test('syncs a batch of simple products without queue buildup', async ({ page }, testInfo) => {
    const created = [];

    try {
      for (let i = 0; i < PERFORMANCE_CONFIG.simpleBatch.count; i++) {
        const product = await createAndPublishSimpleProduct(page, {
          titleSuffix: `Batch-${i + 1}`,
          descriptionRepeat: PERFORMANCE_CONFIG.simpleBatch.descriptionRepeat,
        });
        created.push(product);
      }

      expect(created.length).toBe(PERFORMANCE_CONFIG.simpleBatch.count);
      logTestEnd(testInfo, true);
    } catch (error) {
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      for (const item of created) {
        if (item.productId) {
          await cleanupProduct(item.productId);
        }
      }
    }
  });

  test('syncs products with long names, long descriptions, and large attribute sets', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const attributes = PERFORMANCE_CONFIG.longContent.attributes;
      const expectedVariations = countVariationsFromAttributes(attributes);

      const productName = `Performance Stress Product ${'VeryLongName_'.repeat(8)}${Date.now()}`;
      await setupVariableProduct(page, {
        productName,
        description: buildLongText('This description is intentionally long.', PERFORMANCE_CONFIG.longContent.descriptionRepeat),
        skuPrefix: 'perf-long',
        attributes,
      });

      await page.waitForFunction(
        (count) => document.querySelectorAll('.woocommerce_variation').length >= count,
        expectedVariations,
        { timeout: TIMEOUTS.LONG + TIMEOUTS.LONG }
      );

      await publishProduct(page);

      productId = await resolveProductId(page);
      await assertNoFatalAndSync(
        page,
        productId,
        productName,
        PERFORMANCE_CONFIG.longContent.waitSeconds,
        PERFORMANCE_CONFIG.longContent.maxRetries
      );

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'performance-long-content-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('syncs a variable product with many variations', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const attributes = {
        [PERFORMANCE_CONFIG.manyVariations.attributeName]: PERFORMANCE_CONFIG.manyVariations.attributeValues,
      };
      const expectedVariations = countVariationsFromAttributes(attributes);

      const productName = generateProductName('ManyVariations');
      await setupVariableProduct(page, {
        productName,
        description: 'Variation stress test product.',
        skuPrefix: 'many-vars',
        attributes,
      });

      await page.waitForFunction(
        (count) => document.querySelectorAll('.woocommerce_variation').length >= count,
        expectedVariations,
        { timeout: TIMEOUTS.LONG + TIMEOUTS.LONG }
      );

      const priceInput = page.locator('input.components-text-control__input.wc_input_variations_price');
      await page.locator('button.add_price_for_variations').click();
      await priceInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await priceInput.fill(PERFORMANCE_CONFIG.manyVariations.price);
      await page.locator('button.add_variations_price_button.button-primary').click();

      await publishProduct(page);

      productId = await resolveProductId(page);
      await assertNoFatalAndSync(
        page,
        productId,
        productName,
        PERFORMANCE_CONFIG.manyVariations.waitSeconds,
        PERFORMANCE_CONFIG.manyVariations.maxRetries
      );

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'performance-many-variations-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('repeats product updates to catch queue buildup or timeout issues', async ({ page }, testInfo) => {
    let productId = null;

    try {
      const newProductUrl = `${baseURL}/wp-admin/post-new.php?post_type=product`;
      await page.goto(newProductUrl, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX,
      });
      await waitForProductEditorReady(page, newProductUrl);

      const productName = generateProductName('RepeatedUpdates');
      await setProductTitle(page, productName);
      await setProductDescription(page, 'Initial description for update loop.');
      await page.click('li.general_tab a');
      await page.locator('#_regular_price').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await page.locator('#_regular_price').fill(PERFORMANCE_CONFIG.repeatedUpdates.initialPrice);
      await page.click('li.inventory_tab a');
      await page.locator('#_sku').fill(generateUniqueSKU('update-loop'));

      await publishProduct(page);
      productId = await resolveProductId(page);

      for (let i = 0; i < PERFORMANCE_CONFIG.repeatedUpdates.updates; i++) {
        const editProductUrl = `${baseURL}/wp-admin/post.php?post=${productId}&action=edit`;
        await page.goto(editProductUrl, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.MAX,
        });
        await waitForProductEditorReady(page, editProductUrl);

        await setProductDescription(page, `Updated description iteration ${i + 1}. ${buildLongText('loop', 10)}`);
        await page.click('li.general_tab a');
        await page.locator('#_regular_price').fill(String(Number(PERFORMANCE_CONFIG.repeatedUpdates.initialPrice) + i * PERFORMANCE_CONFIG.repeatedUpdates.priceStep));
        await publishProduct(page);

        await checkForPhpErrors(page);
      }

      await assertNoFatalAndSync(
        page,
        productId,
        productName,
        PERFORMANCE_CONFIG.repeatedUpdates.waitSeconds
      );
      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(page, 'performance-update-loop-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('handles two concurrent browser sessions triggering product and checkout flows', async ({ browser }, testInfo) => {
    const contextA = await browser.newContext();
    const contextB = await browser.newContext();
    const pageA = await contextA.newPage();
    const pageB = await contextB.newPage();

    let productId = null;

    try {
      await Promise.all([
        loginToWordPress(pageA),
        loginToWordPress(pageB),
      ]);

      const newProductUrl = `${baseURL}/wp-admin/post-new.php?post_type=product`;
      await pageA.goto(newProductUrl, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX,
      });
      await waitForProductEditorReady(pageA, newProductUrl);

      const productName = generateProductName('Concurrent');
      await setProductTitle(pageA, productName);
      await setProductDescription(pageA, 'Concurrent session stress test.');
      await pageA.click('li.general_tab a');
      await pageA.locator('#_regular_price').fill(PERFORMANCE_CONFIG.concurrent.price);
      await pageA.click('li.inventory_tab a');
      await pageA.locator('#_sku').fill(generateUniqueSKU('concurrent'));

      const frontendFlow = async (p) => {
        await p.goto(baseURL, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });
        await p.waitForLoadState('domcontentloaded');
      };

      await Promise.all([
        frontendFlow(pageB),
        publishProduct(pageA),
      ]);

      productId = await resolveProductId(pageA);
      await assertNoFatalAndSync(
        pageA,
        productId,
        productName,
        PERFORMANCE_CONFIG.concurrent.waitSeconds
      );

      logTestEnd(testInfo, true);
    } catch (error) {
      await safeScreenshot(pageA, 'performance-concurrent-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
      await contextA.close();
      await contextB.close();
    }
  });
});
