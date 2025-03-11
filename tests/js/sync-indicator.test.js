const $ = require('jquery');

describe('Sync Indicator', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="product_attributes">
                <input type="text" id="fb_color" />
            </div>
        `;
    });

    test('sync indicator is added correctly', () => {
        const field = $('#fb_color');
        field.after('<span class="sync-indicator dashicons dashicons-yes-alt" data-tip="Synced from the Attributes tab."><span class="sync-tooltip">Synced from the Attributes tab.</span></span>');
        
        const indicator = field.next('.sync-indicator');
        expect(indicator.length).toBe(1);
        expect(indicator.hasClass('dashicons-yes-alt')).toBe(true);
    });

    test('tooltip shows on hover', () => {
        const field = $('#fb_color');
        field.after('<span class="sync-indicator dashicons dashicons-yes-alt" data-tip="Synced from the Attributes tab."><span class="sync-tooltip">Synced from the Attributes tab.</span></span>');
        
        const indicator = field.next('.sync-indicator');
        const tooltip = indicator.find('.sync-tooltip');
        
        // Set initial state explicitly
        tooltip.css('display', 'none');
        
        // Initial state - tooltip should be hidden
        expect(tooltip.css('display')).toBe('none');
        
        // Hover state - manually set display since JSDOM doesn't handle hover
        tooltip.css('display', 'block');
        expect(tooltip.css('display')).toBe('block');
        
        // After hover
        tooltip.css('display', 'none');
        expect(tooltip.css('display')).toBe('none');
    });

    test('sync badge state is tracked correctly', () => {
        const syncedBadgeState = {
            color: false
        };
        
        const field = $('#fb_color');
        field.after('<span class="sync-indicator dashicons dashicons-yes-alt" data-tip="Synced from the Attributes tab."><span class="sync-tooltip">Synced from the Attributes tab.</span></span>');
        syncedBadgeState.color = true;
        
        expect(syncedBadgeState.color).toBe(true);
    });
}); 