/**
 * Event product helpers (variable/grouped fixtures and storefront interactions)
 */

const { exec, execSync } = require('child_process');
const { promisify } = require('util');
const { TIMEOUTS } = require('../constants/timeouts');

const execAsync = promisify(exec);

function shellEscape(value) {
  return `'${String(value).replace(/'/g, `'"'"'`)}'`;
}

function parseExecWpJson(stdout) {
  const trimmed = (stdout || '').trim();
  if (!trimmed) {
    throw new Error('Empty WP response');
  }

  const candidates = [];

  if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
    candidates.push(trimmed);
  }

  const matches = trimmed.match(/\{[\s\S]*\}/g) || [];
  candidates.push(...matches.reverse());

  for (const candidate of candidates) {
    try {
      return JSON.parse(candidate);
    } catch {
      // Try next candidate.
    }
  }

  throw new Error(`No valid JSON object found in WP response: ${trimmed.slice(0, 400)}`);
}

async function runPhpInWp(phpCode) {
  const wordpressPath = process.env.WORDPRESS_PATH;
  if (!wordpressPath) {
    throw new Error('WORDPRESS_PATH is required');
  }

  const phpBin = process.env.PHP_BIN || 'php';
  const usePhpNoIni = process.env.USE_PHP_NO_INI === '1';
  const wpCliPath = process.env.WP_CLI_PATH || execSync('command -v wp', { encoding: 'utf8' }).trim();

  const phpNoIniFlag = usePhpNoIni ? '-n ' : '';
  const command = `${phpBin} ${phpNoIniFlag}${shellEscape(wpCliPath)} eval ${shellEscape(phpCode)} --path=${shellEscape(wordpressPath)} --allow-root`;
  const { stdout } = await execAsync(command, { cwd: __dirname, env: process.env });

  return parseExecWpJson(stdout);
}

async function createVariableProductEventFixture() {
  const stamp = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const name = `E2E Variable Event Product ${stamp}`;
  const parentSku = `E2E-VAR-PARENT-${stamp}`;
  const smallSku = `E2E-VAR-SM-${stamp}`;
  const largeSku = `E2E-VAR-LG-${stamp}`;

  const phpCode = `
    if ( ! class_exists( 'WC_Product_Variable' ) ) { echo wp_json_encode( array( 'success' => false, 'error' => 'WooCommerce not active' ) ); return; }

    $parent = new WC_Product_Variable();
    $parent->set_name( '${name}' );
    $parent->set_sku( '${parentSku}' );
    $parent->set_status( 'publish' );
    $parent->set_catalog_visibility( 'visible' );
    $parent->set_description( 'E2E variable event test product.' );
    $parent->set_short_description( 'E2E variable event test product.' );

    $attribute = new WC_Product_Attribute();
    $attribute->set_name( 'Size' );
    $attribute->set_options( array( 'Small', 'Large' ) );
    $attribute->set_visible( true );
    $attribute->set_variation( true );

    $parent->set_attributes( array( $attribute ) );
    $parent_id = $parent->save();

    if ( ! $parent_id ) { echo wp_json_encode( array( 'success' => false, 'error' => 'Failed to create parent variable product' ) ); return; }

    $defs = array(
      array( 'option' => 'Small', 'sku' => '${smallSku}', 'price' => '21.00' ),
      array( 'option' => 'Large', 'sku' => '${largeSku}', 'price' => '27.00' )
    );

    $variations = array();

    foreach ( $defs as $def ) {
      $variation = new WC_Product_Variation();
      $variation->set_parent_id( $parent_id );
      $variation->set_attributes( array( 'size' => $def['option'] ) );
      $variation->set_sku( $def['sku'] );
      $variation->set_regular_price( $def['price'] );
      $variation->set_price( $def['price'] );
      $variation->set_manage_stock( true );
      $variation->set_stock_quantity( 20 );
      $variation->set_stock_status( 'instock' );

      $variation_id = $variation->save();

      $variations[] = array(
        'id' => $variation_id,
        'option' => $def['option'],
        'sku' => $def['sku'],
        'retailer_id' => WC_Facebookcommerce_Utils::get_fb_retailer_id( wc_get_product( $variation_id ) )
      );
    }

    $parent_product = wc_get_product( $parent_id );

    echo wp_json_encode( array(
      'success' => true,
      'parent_id' => $parent_id,
      'parent_url' => get_permalink( $parent_id ),
      'parent_retailer_id' => WC_Facebookcommerce_Utils::get_fb_retailer_id( $parent_product ),
      'attribute_slug' => 'size',
      'variations' => $variations
    ) );
  `;

  const result = await runPhpInWp(phpCode);

  if (!result.success) {
    throw new Error(`Failed to create variable event fixture: ${result.error || 'unknown error'}`);
  }

  return {
    parentId: result.parent_id,
    parentUrl: result.parent_url,
    parentRetailerId: result.parent_retailer_id,
    attributeSlug: result.attribute_slug,
    variations: result.variations || [],
    cleanupProductIds: [result.parent_id],
  };
}

async function createGroupedProductEventFixture() {
  const stamp = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const parentName = `E2E Grouped Event Product ${stamp}`;

  const phpCode = `
    if ( ! class_exists( 'WC_Product_Grouped' ) ) { echo wp_json_encode( array( 'success' => false, 'error' => 'WooCommerce not active' ) ); return; }

    $child_defs = array(
      array( 'name' => 'E2E Grouped Child One ${stamp}', 'sku' => 'E2E-GRP-CH1-${stamp}', 'price' => '13.00' ),
      array( 'name' => 'E2E Grouped Child Two ${stamp}', 'sku' => 'E2E-GRP-CH2-${stamp}', 'price' => '17.00' )
    );

    $child_ids = array();
    $children = array();

    foreach ( $child_defs as $child_def ) {
      $child = new WC_Product_Simple();
      $child->set_name( $child_def['name'] );
      $child->set_sku( $child_def['sku'] );
      $child->set_status( 'publish' );
      $child->set_catalog_visibility( 'visible' );
      $child->set_regular_price( $child_def['price'] );
      $child->set_price( $child_def['price'] );
      $child->set_manage_stock( true );
      $child->set_stock_quantity( 25 );
      $child->set_stock_status( 'instock' );

      $child_id = $child->save();
      $child_ids[] = $child_id;

      $saved_child = wc_get_product( $child_id );
      $children[] = array(
        'id' => $child_id,
        'name' => $saved_child ? $saved_child->get_name() : $child_def['name'],
        'sku' => $child_def['sku'],
        'retailer_id' => $saved_child ? WC_Facebookcommerce_Utils::get_fb_retailer_id( $saved_child ) : (string) $child_id,
        'price' => $child_def['price']
      );
    }

    $grouped = new WC_Product_Grouped();
    $grouped->set_name( '${parentName}' );
    $grouped->set_sku( 'E2E-GRP-PARENT-${stamp}' );
    $grouped->set_status( 'publish' );
    $grouped->set_catalog_visibility( 'visible' );
    $grouped->set_description( 'E2E grouped event test product.' );
    $grouped->set_short_description( 'E2E grouped event test product.' );
    $grouped->set_children( $child_ids );

    $grouped_id = $grouped->save();

    if ( ! $grouped_id ) { echo wp_json_encode( array( 'success' => false, 'error' => 'Failed to create grouped product' ) ); return; }

    $grouped_product = wc_get_product( $grouped_id );

    echo wp_json_encode( array(
      'success' => true,
      'grouped_id' => $grouped_id,
      'grouped_url' => get_permalink( $grouped_id ),
      'grouped_retailer_id' => WC_Facebookcommerce_Utils::get_fb_retailer_id( $grouped_product ),
      'children' => $children,
      'cleanup_ids' => array_merge( array( $grouped_id ), $child_ids )
    ) );
  `;

  const result = await runPhpInWp(phpCode);

  if (!result.success) {
    throw new Error(`Failed to create grouped event fixture: ${result.error || 'unknown error'}`);
  }

  return {
    groupedId: result.grouped_id,
    groupedUrl: result.grouped_url,
    groupedRetailerId: result.grouped_retailer_id,
    children: result.children || [],
    cleanupProductIds: result.cleanup_ids || [result.grouped_id],
  };
}

async function selectVariationByLabel(page, { attributeSlug = 'size', label }) {
  const variationSelect = page.locator(`form.variations_form select[name='attribute_${attributeSlug}']`).first();
  await variationSelect.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  const selectedValue = await variationSelect.evaluate((select, targetLabel) => {
    const options = Array.from(select.options || []);
    const byLabel = options.find(option => option.textContent.trim() === targetLabel && option.value);
    if (byLabel) {
      return byLabel.value;
    }

    const byValue = options.find(option => option.value && option.value.toLowerCase() === String(targetLabel).toLowerCase());
    return byValue ? byValue.value : null;
  }, label);

  if (!selectedValue) {
    throw new Error(`Variation option not found for label: ${label}`);
  }

  await variationSelect.selectOption(selectedValue);

  const variationIdField = page.locator("input[name='variation_id']").first();
  await variationIdField.waitFor({ state: 'attached', timeout: TIMEOUTS.NORMAL });
  await expectVariationResolved(page, variationIdField);

  return {
    selectedValue,
    variationId: Number(await variationIdField.inputValue()),
  };
}

async function expectVariationResolved(page, variationIdField) {
  await page.waitForFunction(
    (selector) => {
      const input = document.querySelector(selector);
      if (!input) return false;
      const id = parseInt(input.value || '0', 10);
      return Number.isFinite(id) && id > 0;
    },
    "input[name='variation_id']",
    { timeout: TIMEOUTS.LONG }
  );

  await page.waitForTimeout(TIMEOUTS.INSTANT);

  if (!(await variationIdField.inputValue())) {
    throw new Error('Variation was not resolved after selecting attributes');
  }
}

async function setGroupedProductQuantity(page, childProductId, quantity = 1) {
  const byId = page.locator(`input.qty[name='quantity[${childProductId}]']`).first();
  const fallback = page.locator('table.group_table input.qty').first();
  const qtyInput = (await byId.count()) > 0 ? byId : fallback;

  await qtyInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await qtyInput.fill(String(quantity));
  await page.waitForTimeout(TIMEOUTS.INSTANT);
}

module.exports = {
  createVariableProductEventFixture,
  createGroupedProductEventFixture,
  selectVariationByLabel,
  setGroupedProductQuantity,
};
