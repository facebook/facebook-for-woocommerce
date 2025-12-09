/**
 * ExpectedDataBuilder - Generates expected event data for validation
 *
 * Fetches REAL data: product info, versions, customer data
 * Simple, clean, comprehensive
 */

const crypto = require('crypto');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Test customer data (from workflow setup)
 */
const TEST_CUSTOMER = {
    email: 'customer@test.com',
    external_id: '2',
    city: 'sanfrancisco',
    state: 'ca',
    zip: '94102',
    country: 'us',
    phone: '4155551234',
};

/**
 * SHA256 hash utility
 */
function hash(value) {
    return crypto.createHash('sha256').update(value.toLowerCase()).digest('hex');
}

/**
 * Pre-computed hashes
 */
const HASHED = {
    em: hash(TEST_CUSTOMER.email),
    external_id: hash(TEST_CUSTOMER.external_id),
    ct: hash(TEST_CUSTOMER.city),
    zp: hash(TEST_CUSTOMER.zip),
    st: hash(TEST_CUSTOMER.state),
    ph: hash(TEST_CUSTOMER.phone),
    country: hash(TEST_CUSTOMER.country),
};

class ExpectedDataBuilder {
    constructor() {
        this.productCache = {};
        this.versionsCache = null;
    }

    /**
     * Get complete expected event (user_data + custom_data)
     */
    async getExpectedEvent(eventType, source, options = {}) {
        const user_data = await this.getUserData(eventType, source);
        const custom_data = await this.getCustomData(eventType, source, options);

        return { user_data, custom_data };
    }

    /**
     * Get expected user_data
     */
    async getUserData(eventType, source) {
        const baseUserData = {
            em: HASHED.em,
            external_id: HASHED.external_id,
            ct: HASHED.ct,
            zp: HASHED.zp,
        };

        // Lead event only has email
        if (eventType === 'Lead') {
            return { em: HASHED.em };
        }

        // Pixel uses 'cn', CAPI uses 'country'
        if (source === 'pixel') {
            return {
                ...baseUserData,
                cn: HASHED.country,
            };
        } else {
            return {
                ...baseUserData,
                st: HASHED.st,
                ph: HASHED.ph,
                country: HASHED.country,
            };
        }
    }

    /**
     * Get expected custom_data
     */
    async getCustomData(eventType, source, options = {}) {
        const versions = await this.getVersions();

        switch (eventType) {
            case 'PageView':
                return this.getPageViewCustomData(source, versions);

            case 'ViewContent':
            case 'AddToCart':
                return await this.getSingleProductCustomData(source, versions, options.productId);

            case 'ViewCategory':
                return await this.getViewCategoryCustomData(source, versions);

            case 'Search':
                return await this.getSearchCustomData(source, versions, options.searchString);

            case 'InitiateCheckout':
                return await this.getInitiateCheckoutCustomData(source, versions, options.productId);

            case 'Purchase':
                return await this.getPurchaseCustomData(source, options.productId);

            case 'Lead':
                return source === 'pixel' ? { ...versions } : {};

            default:
                throw new Error(`Unknown event type: ${eventType}`);
        }
    }

    /**
     * PageView custom data
     */
    getPageViewCustomData(source, versions) {
        if (source === 'capi') return {};

        return {
            ...versions,
            user_data: {
                em: TEST_CUSTOMER.email,
                external_id: TEST_CUSTOMER.external_id,
                ct: TEST_CUSTOMER.city,
                zp: TEST_CUSTOMER.zip,
                cn: TEST_CUSTOMER.country,
            },
        };
    }

    /**
     * Single product events (ViewContent, AddToCart)
     */
    async getSingleProductCustomData(source, versions, productId) {
        const product = await this.getProduct(productId);

        const data = {
            content_name: product.name,
            content_ids: [`wc_post_id_${productId}`],
            content_type: 'product',
            content_category: product.category,
            value: parseFloat(product.price),
            currency: 'USD',
        };

        // Pixel uses object notation, CAPI uses array
        if (source === 'pixel') {
            data.contents = {
                "0": { id: `wc_post_id_${productId}`, quantity: 1 }
            };
            return { ...versions, ...data };
        } else {
            data.contents = [{ id: `wc_post_id_${productId}`, quantity: 1 }];
            return data;
        }
    }

    /**
     * ViewCategory custom data
     */
    async getViewCategoryCustomData(source, versions) {
        const products = await this.getAllProducts();
        const totalValue = products.reduce((sum, p) => sum + parseFloat(p.price), 0);

        const data = {
            content_name: products.map(p => p.name),
            content_category: 'Uncategorized',
            content_ids: products.map(p => `wc_post_id_${p.id}`),
            content_type: 'product',
            value: totalValue,
            currency: 'USD',
        };

        // Format contents
        if (source === 'pixel') {
            data.contents = {};
            products.forEach((p, i) => {
                data.contents[i.toString()] = { id: `wc_post_id_${p.id}`, quantity: 1 };
            });
            return { ...versions, ...data };
        } else {
            data.contents = products.map(p => ({ id: `wc_post_id_${p.id}`, quantity: 1 }));
            return data;
        }
    }

    /**
     * Search custom data
     */
    async getSearchCustomData(source, versions, searchString) {
        const products = await this.getAllProducts();
        const totalValue = products.reduce((sum, p) => sum + parseFloat(p.price), 0);

        const data = {
            search_string: searchString,
            content_name: products.map(p => p.name),
            content_category: 'Uncategorized',
            content_ids: products.map(p => `wc_post_id_${p.id}`),
            content_type: 'product',
            value: totalValue,
            currency: 'USD',
        };

        // Format contents
        if (source === 'pixel') {
            data.contents = {};
            products.forEach((p, i) => {
                data.contents[i.toString()] = { id: `wc_post_id_${p.id}`, quantity: 1 };
            });
            return { ...versions, ...data };
        } else {
            data.contents = products.map(p => ({ id: `wc_post_id_${p.id}`, quantity: 1 }));
            return data;
        }
    }

    /**
     * InitiateCheckout custom data
     */
    async getInitiateCheckoutCustomData(source, versions, productId) {
        const product = await this.getProduct(productId);

        const data = {
            content_name: product.name,
            content_ids: [`wc_post_id_${productId}`],
            content_type: 'product',
            content_category: product.category,
            num_items: 1,
            value: parseFloat(product.price),
            currency: 'USD',
        };

        // Format contents
        if (source === 'pixel') {
            data.contents = {
                "0": { id: `wc_post_id_${productId}`, quantity: 1 }
            };
            return { ...versions, ...data };
        } else {
            data.contents = [{ id: `wc_post_id_${productId}`, quantity: 1 }];
            return data;
        }
    }

    /**
     * Purchase custom data
     */
    async getPurchaseCustomData(source, productId) {
        const product = await this.getProduct(productId);

        const data = {
            content_name: product.name,
            content_ids: [`wc_post_id_${productId}`],
            content_type: 'product',
            value: parseFloat(product.price),
            currency: 'USD',
        };

        // CAPI only for Purchase
        data.contents = [{ id: `wc_post_id_${productId}`, quantity: 1 }];
        return data;
    }

    /**
     * Fetch product data from WordPress
     */
    async getProduct(productId) {
        if (this.productCache[productId]) {
            return this.productCache[productId];
        }

        try {
            const result = execSync(
                `wp post get ${productId} --field=post_title --allow-root`,
                { encoding: 'utf-8', cwd: process.env.WORDPRESS_PATH || '/tmp/wordpress' }
            ).trim();

            const price = execSync(
                `wp post meta get ${productId} _price --allow-root`,
                { encoding: 'utf-8', cwd: process.env.WORDPRESS_PATH || '/tmp/wordpress' }
            ).trim();

            const category = 'Uncategorized';  // Default category from test setup

            this.productCache[productId] = {
                id: productId,
                name: result,
                price: price,
                category: category,
            };

            return this.productCache[productId];
        } catch (error) {
            throw new Error(`Failed to fetch product ${productId}: ${error.message}`);
        }
    }

    /**
     * Get all products in catalog
     */
    async getAllProducts() {
        try {
            const productIds = execSync(
                `wp post list --post_type=product --field=ID --allow-root`,
                { encoding: 'utf-8', cwd: process.env.WORDPRESS_PATH || '/tmp/wordpress' }
            ).trim().split('\n').map(id => parseInt(id));

            const products = await Promise.all(productIds.map(id => this.getProduct(id)));
            return products.sort((a, b) => a.id - b.id);  // Sort by ID
        } catch (error) {
            throw new Error(`Failed to fetch products: ${error.message}`);
        }
    }

    /**
     * Get version and pluginVersion from actual sources
     */
    async getVersions() {
        if (this.versionsCache) {
            return this.versionsCache;
        }

        try {
            // Get WooCommerce version
            const wcVersion = execSync(
                `wp plugin get woocommerce --field=version --allow-root`,
                { encoding: 'utf-8', cwd: process.env.WORDPRESS_PATH || '/tmp/wordpress' }
            ).trim();

            // Get plugin version from main plugin file
            const pluginPath = path.join(__dirname, '../../../facebook-for-woocommerce.php');
            const pluginContent = fs.readFileSync(pluginPath, 'utf-8');
            const versionMatch = pluginContent.match(/Version:\s*(.+)/);
            const pluginVersion = versionMatch ? versionMatch[1].trim() : null;

            this.versionsCache = {
                source: 'woocommerce_0',
                version: wcVersion,
                pluginVersion: pluginVersion,
            };

            return this.versionsCache;
        } catch (error) {
            throw new Error(`Failed to get versions: ${error.message}`);
        }
    }

    /**
     * Get fields to exclude from comparison (dynamic fields that can't be known)
     */
    static getExcludedFields(eventType, source) {
        // Always exclude these dynamic fields
        const commonExcluded = ['fbp', 'fbc', 'event_id', 'event_time', 'capturedAt'];

        // Source-specific exclusions
        const sourceExcluded = source === 'capi'
            ? ['client_ip_address', 'client_user_agent', 'action_source', 'event_source_url']
            : ['api_status', 'api_ok'];

        // Event-specific exclusions
        const eventExcluded = eventType === 'Purchase' ? ['order_id'] : [];

        return [...commonExcluded, ...sourceExcluded, ...eventExcluded];
    }

    /**
     * Simple object comparison excluding dynamic fields
     */
    static compareObjects(expected, actual, excludeFields = []) {
        const differences = [];

        function compare(exp, act, path = '') {
            // Skip excluded fields
            if (excludeFields.some(field => path.endsWith(field))) {
                return;
            }

            // Both are objects
            if (exp && act && typeof exp === 'object' && typeof act === 'object') {
                // Arrays
                if (Array.isArray(exp) && Array.isArray(act)) {
                    if (exp.length !== act.length) {
                        differences.push(`${path}: array length mismatch (expected ${exp.length}, got ${act.length})`);
                    }
                    exp.forEach((item, i) => {
                        if (i < act.length) {
                            compare(item, act[i], `${path}[${i}]`);
                        }
                    });
                    return;
                }

                // Objects
                const allKeys = new Set([...Object.keys(exp), ...Object.keys(act)]);
                for (const key of allKeys) {
                    const newPath = path ? `${path}.${key}` : key;

                    // Skip excluded
                    if (excludeFields.includes(key)) continue;

                    if (!(key in exp)) {
                        differences.push(`${newPath}: unexpected field in actual`);
                    } else if (!(key in act)) {
                        differences.push(`${newPath}: missing in actual`);
                    } else {
                        compare(exp[key], act[key], newPath);
                    }
                }
                return;
            }

            // Primitives
            if (exp !== act) {
                differences.push(`${path}: expected "${exp}" but got "${act}"`);
            }
        }

        compare(expected, actual);
        return { matches: differences.length === 0, differences };
    }
}

module.exports = ExpectedDataBuilder;
