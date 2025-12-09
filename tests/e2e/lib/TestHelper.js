class TestHelper {
    constructor(page) {
        this.page = page;
        this.productId = null;
        this.productData = null;
    }

    // use WP-CLI via execute_command
    async getProduct(productId) {
        const result = await execute_command({
            command: `cd ${process.env.WORDPRESS_PATH} && wp post get ${productId} --format=json --allow-root`,
            summary: 'Get product data via WP-CLI'
        });

        return JSON.parse(result.stdout);
    }

    async getUserData(eventSource, eventType){
        // Based on the event source (Pixel/CAPI) and event type(PageView etc), get the user data based on the schema

    }

    async getCustomData(eventSource, eventType){
        // Based on the event source (Pixel/CAPI) and event type(PageView etc), get the custom data based on the schema

    }

    async getFieldValue(field){

    }
}
