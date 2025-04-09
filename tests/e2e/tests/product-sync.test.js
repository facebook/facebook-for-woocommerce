const { test, expect } = require('@playwright/test');

test.describe('Facebook for WooCommerce Product Sync', () => {
  const productData = {
    name: 'Test Simple Product',
    regularPrice: '19.99',
    description: 'Test product description',
    shortDescription: 'Short description'
  };

  test.beforeEach(async ({ page }) => {
    // Login to WP Admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', process.env.WORDPRESS_USER);
    await page.fill('#user_pass', process.env.WORDPRESS_PASS);
    await page.click('#wp-submit');
    await page.waitForSelector('#wpadminbar');
  });

  test('should sync a simple product to Facebook', async ({ page }) => {
    // Navigate to Add New Product
    await page.goto('/wp-admin/post-new.php?post_type=product');
    await page.waitForSelector('.woocommerce-product-data');

    // Fill product details
    await page.fill('#title', productData.name);
    await page.fill('#_regular_price', productData.regularPrice);
    await page.click('button[data-id="description"]');
    await page.fill('.wp-editor-area', productData.description);
    
    // Enable Facebook sync
    await page.click('#fbsync-enabled');
    
    // Publish product
    await page.click('#publish');
    await page.waitForSelector('.updated.notice');

    // Verify in WooCommerce
    const productTitle = await page.$eval('.post-title', el => el.textContent);
    expect(productTitle).toContain(productData.name);

    // Check Facebook sync
    await page.goto('/wp-admin/admin.php?page=wc-facebook');
    await page.waitForSelector('.sync-status');
    
    // Get Facebook product data
    const catalogId = await page.$eval('#woocommerce_facebook_catalog_id', el => el.value);
    const response = await page.request.get(
      `https://graph.facebook.com/v18.0/${catalogId}/products`,
      {
        headers: {
          'Authorization': `Bearer ${process.env.FACEBOOK_ACCESS_TOKEN}`
        }
      }
    );
    
    const fbProducts = await response.json();
    const syncedProduct = fbProducts.data.find(p => p.name === productData.name);
    
    expect(syncedProduct).toBeTruthy();
    expect(syncedProduct.price).toBe(`${productData.regularPrice} USD`);
  });

  test('should update product and sync changes to Facebook', async ({ page }) => {
    // Edit product
    await page.goto('/wp-admin/edit.php?post_type=product');
    await page.click(`text=${productData.name}`);
    
    const newPrice = '24.99';
    await page.fill('#_regular_price', newPrice);
    await page.click('#publish');
    
    // Check sync status
    await page.goto('/wp-admin/admin.php?page=wc-facebook');
    await page.waitForSelector('.sync-status');

    // Verify in Facebook
    const catalogId = await page.$eval('#woocommerce_facebook_catalog_id', el => el.value);
    const response = await page.request.get(
      `https://graph.facebook.com/v18.0/${catalogId}/products`,
      {
        headers: {
          'Authorization': `Bearer ${process.env.FACEBOOK_ACCESS_TOKEN}`
        }
      }
    );
    
    const fbProducts = await response.json();
    const updatedProduct = fbProducts.data.find(p => p.name === productData.name);
    
    expect(updatedProduct.price).toBe(`${newPrice} USD`);
  });

  test('should delete product and remove from Facebook', async ({ page }) => {
    // Delete product
    await page.goto('/wp-admin/edit.php?post_type=product');
    await page.click(`text=${productData.name}`);
    await page.click('#delete-action a');
    
    // Check removal from Facebook
    await page.goto('/wp-admin/admin.php?page=wc-facebook');
    await page.waitForSelector('.sync-status');
    
    const catalogId = await page.$eval('#woocommerce_facebook_catalog_id', el => el.value);
    const response = await page.request.get(
      `https://graph.facebook.com/v18.0/${catalogId}/products`,
      {
        headers: {
          'Authorization': `Bearer ${process.env.FACEBOOK_ACCESS_TOKEN}`
        }
      }
    );
    
    const fbProducts = await response.json();
    const deletedProduct = fbProducts.data.find(p => p.name === productData.name);
    expect(deletedProduct).toBeFalsy();
  });
}); 