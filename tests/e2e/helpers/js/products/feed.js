/**
 * Product feed CSV generation helpers for E2E tests
 */

const fs = require('fs');
const path = require('path');
const os = require('os');
const { generateUniqueSKU } = require('./crud');

/**
 * Generate a product feed CSV file
 * @param {number} productCount - Number of products to generate
 * @param {number} variableProductPercentage - Percentage of variable products (0-1)
 * @param {string} categoryName - Category name for products
 * @returns {Promise<Object>} Feed file details
 */
async function generateProductFeedCSV(productCount = 10, variableProductPercentage = 0.3, categoryName = "feed-test-products") {
  console.log(`📝 Generating product feed CSV with ${productCount} products (${Math.round(variableProductPercentage * 100)}% variable)...`);

  const headers = [
    'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
    'Short description', 'Description', 'Date sale price starts', 'Date sale price ends',
    'Tax status', 'Tax class', 'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
    'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)', 'Height (cm)',
    'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price', 'Categories',
    'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry days', 'Parent',
    'Grouped products', 'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position',
    'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
    'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global'
  ];

  const rows = [headers];
  let productId = 1000;
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const timestamp = new Date().getTime();

  const variableProductCount = Math.floor(productCount * variableProductPercentage);
  const simpleProductCount = productCount - variableProductCount;

  console.log(`   - ${simpleProductCount} simple products`);
  console.log(`   - ${variableProductCount} variable products`);

  // Generate simple products
  for (let i = 0; i < simpleProductCount; i++) {
    productId++;
    const sku = generateUniqueSKU('Simple');
    const name = `Feed Test Simple Product ${i + 1}`;
    const price = (Math.random() * 50 + 10).toFixed(2);

    rows.push([
      productId, 'simple', sku, name, '1', '0', 'visible',
      `Short description for ${name}`,
      `This is a test product created from feed file for E2E testing. Product number ${i + 1}.`,
      '', '', 'taxable', '', '1', Math.floor(Math.random() * 100 + 10), '', '0', '0',
      '', '', '', '', '1', '', '', price, categoryName, '', '', '', '', '', '',
      '', '', '', '', '0', '', '', '', '', '', '', '', ''
    ]);
  }

  // Generate variable products with variations
  for (let i = 0; i < variableProductCount; i++) {
    productId++;
    const parentId = productId;
    const sku = generateUniqueSKU('Variable');
    const name = `Feed Test Variable Product ${i + 1}`;
    const basePrice = (Math.random() * 50 + 20).toFixed(2);

    // Parent variable product
    rows.push([
      parentId, 'variable', sku, name, '1', '0', 'visible',
      `Short description for ${name}`,
      `This is a variable test product created from feed file for E2E testing. Product number ${i + 1}.`,
      '', '', 'taxable', '', '1', '', '', '0', '0', '', '', '', '', '1', '', '', '',
      categoryName, '', '', '', '', '', '', '', '', '', '', '0',
      'Color', 'Red, Blue, Green', '1', '1', '', '', '', ''
    ]);

    // Generate variations
    const colors = ['Red', 'Blue', 'Green'];
    let position = 1;

    for (const color of colors) {
      productId++;
      const variationPrice = (parseFloat(basePrice) + Math.random() * 10).toFixed(2);

      rows.push([
        productId, 'variation', `${sku}-${color}`, `${name} - ${color}`, '1', '0', 'visible',
        '', `Variation: ${color}`, '', '', 'taxable', 'parent', '1',
        Math.floor(Math.random() * 50 + 5), '', '0', '0', '', '', '', '', '0', '', '',
        variationPrice, '', '', '', '', '', '', `id:${parentId}`, '', '', '', '', '',
        position, 'Color', color, '', '1', '', '', '', ''
      ]);
      position++;
    }
  }

  // Convert to CSV format
  const csvContent = rows.map(row =>
    row.map(cell => {
      const cellStr = String(cell);
      if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
        return `"${cellStr.replace(/"/g, '""')}"`;
      }
      return cellStr;
    }).join(',')
  ).join('\n');

  // Save to temp directory
  const tempDir = os.tmpdir();
  const fileName = `product-feed-${runId}-${timestamp}.csv`;
  const filePath = path.join(tempDir, fileName);

  fs.writeFileSync(filePath, csvContent, 'utf8');

  console.log(`✅ Generated feed file: ${filePath}`);
  console.log(`   Total rows: ${rows.length} (including header)`);

  return {
    filePath,
    fileName,
    productCount: rows.length - 1,
    simpleProductCount,
    variableProductCount
  };
}

/**
 * Delete a feed file
 * @param {string} filePath - Path to the feed file
 */
async function deleteFeedFile(filePath) {
  try {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
      console.log(`✅ Deleted feed file: ${filePath}`);
    }
  } catch (error) {
    console.log(`⚠️ Failed to delete feed file: ${error.message}`);
  }
}

/**
 * Generate product update CSV based on existing SKUs
 * @param {Array} existingProducts - Existing products to update
 * @param {string} categoryName - Category name
 * @returns {Promise<Object>} Update feed file details
 */
async function generateProductUpdateCSV(existingProducts, categoryName = "feed-test-products") {
  console.log(`📝 Generating product update CSV with ${existingProducts.length} products...`);

  const headers = [
    'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
    'Short description', 'Description', 'Date sale price starts', 'Date sale price ends',
    'Tax status', 'Tax class', 'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
    'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)', 'Height (cm)',
    'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price', 'Categories',
    'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry days', 'Parent',
    'Grouped products', 'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position',
    'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
    'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global'
  ];

  const rows = [headers];
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const timestamp = new Date().getTime();

  for (const product of existingProducts) {
    const updatedPrice = (parseFloat(product.price) + 10).toFixed(2);

    rows.push([
      '', product.type, product.sku, product.name, '1', '0', 'visible',
      `Short description for ${product.name}`, product.description, '', '', 'taxable', '',
      '1', product.stock, '', '0', '0', '', '', '', '', '1', '', '', updatedPrice,
      categoryName, '', '', '', '', '', '', '', '', '', '', '', '0',
      '', '', '', '', '', '', '', ''
    ]);
  }

  const csvContent = rows.map(row =>
    row.map(cell => {
      const cellStr = String(cell);
      if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
        return `"${cellStr.replace(/"/g, '""')}"`;
      }
      return cellStr;
    }).join(',')
  ).join('\n');

  const tempDir = os.tmpdir();
  const fileName = `product-update-${runId}-${timestamp}.csv`;
  const filePath = path.join(tempDir, fileName);

  fs.writeFileSync(filePath, csvContent, 'utf8');

  console.log(`✅ Generated update feed file: ${filePath}`);
  console.log(`   Total rows: ${rows.length} (including header)`);

  return {
    filePath,
    fileName,
    productCount: rows.length - 1
  };
}

module.exports = {
  generateProductFeedCSV,
  deleteFeedFile,
  generateProductUpdateCSV
};
