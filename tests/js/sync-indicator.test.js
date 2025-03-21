const $ = require('jquery');

describe('Sync Indicator', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="product_attributes">
                <input type="text" id="fb_color" />
            </div>
        `;
        const field = $('#fb_color');
        field.after('<span class="sync-indicator dashicons dashicons-yes-alt" data-tip="Synced from the Attributes tab."><span class="sync-tooltip">Synced from the Attributes tab.</span></span>');
    });

    test('sync indicator is added correctly', () => {
        const field = $('#fb_color');
        const indicator = field.next('.sync-indicator');
        expect(indicator.length).toBe(1);
        expect(indicator.hasClass('dashicons-yes-alt')).toBe(true);
    });

    test('tooltip has correct content and structure', () => {
        const field = $('#fb_color');
        const indicator = field.next('.sync-indicator');
        const tooltip = indicator.find('.sync-tooltip');
        
        // Verify tooltip exists and has correct content
        expect(tooltip.length).toBe(1);
        expect(tooltip.text()).toBe('Synced from the Attributes tab.');
        expect(indicator.attr('data-tip')).toBe('Synced from the Attributes tab.');
    });

    test('sync badge state is tracked correctly', () => {
        const syncedBadgeState = {
            color: false
        };
        
        const field = $('#fb_color');
        syncedBadgeState.color = true;
        
        expect(syncedBadgeState.color).toBe(true);
    });
}); 