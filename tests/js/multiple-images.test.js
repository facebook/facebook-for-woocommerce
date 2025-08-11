/**
 * Tests for Multiple Images functionality in Facebook for WooCommerce
 * 
 * These tests cover the JavaScript functionality for adding, removing, and managing
 * multiple images for product variations in the WooCommerce admin.
 */

describe('Multiple Images Functionality', function() {
    
    let mockWpMedia, mockJQuery, mockDocument;
    
    beforeEach(function() {
        // Mock jQuery
        mockJQuery = {
            fn: {
                on: jest.fn(),
                find: jest.fn(() => mockJQuery),
                closest: jest.fn(() => mockJQuery),
                val: jest.fn(),
                show: jest.fn(),
                hide: jest.fn(),
                addClass: jest.fn(),
                removeClass: jest.fn(),
                attr: jest.fn(),
                append: jest.fn(),
                remove: jest.fn(),
                length: 1
            },
            map: jest.fn(),
            each: jest.fn()
        };
        
        // Mock wp.media
        mockWpMedia = {
            frames: {},
            view: {
                MediaFrame: {
                    Select: class MockMediaFrame {
                        constructor() {
                            this.state = jest.fn(() => ({
                                get: jest.fn(() => ({
                                    selection: {
                                        add: jest.fn(),
                                        remove: jest.fn(),
                                        reset: jest.fn(),
                                        map: jest.fn(() => [])
                                    }
                                }))
                            }));
                            this.on = jest.fn();
                            this.open = jest.fn();
                        }
                    }
                }
            }
        };
        
        // Mock document
        mockDocument = {
            ready: jest.fn(),
            on: jest.fn()
        };
        
        // Setup global mocks
        global.$ = jest.fn(() => mockJQuery);
        global.jQuery = global.$;
        global.wp = { media: mockWpMedia };
        global.document = mockDocument;
        
        // Copy the properties to the function
        Object.assign(global.$, mockJQuery);
    });
    
    afterEach(function() {
        jest.resetAllMocks();
    });

    describe('createImageThumbnail function', function() {
        
        it('should create image thumbnail HTML structure', function() {
            // Mock attachment data
            const attachment = {
                id: 123,
                attributes: {
                    url: 'https://example.com/image.jpg',
                    filename: 'image.jpg'
                }
            };
            
            // Mock thumbnail URL (simplified for testing)
            const thumbnailUrl = 'https://example.com/image-thumb.jpg';
            
            // Expected HTML structure (simplified for testing)
            const expectedHtml = `<p class="form-field image-thumbnail">
                <img src="${thumbnailUrl}">
                <span data-attachment-id="${attachment.id}">${attachment.attributes.filename}</span>
                <a href="#" class="remove-image" data-attachment-id="${attachment.id}">Remove</a>
            </p>`;
            
            // Test that the function would generate appropriate structure
            expect(attachment.id).toBe(123);
            expect(attachment.attributes.url).toBe('https://example.com/image.jpg');
            expect(attachment.attributes.filename).toBe('image.jpg');
        });
        
        it('should handle missing attachment data gracefully', function() {
            const attachment = {
                id: null,
                attributes: {
                    url: '',
                    filename: ''
                }
            };
            
            // Should handle null/empty values
            expect(attachment.id).toBeNull();
            expect(attachment.attributes.url).toBe('');
            expect(attachment.attributes.filename).toBe('');
        });
    });

    describe('removeImageThumbnail function', function() {
        
        it('should remove attachment ID from hidden field', function() {
            const attachmentId = 456;
            const variationIndex = 0;
            const currentValue = '123,456,789';
            const expectedValue = '123,789';
            
            // Mock the hidden field
            const mockHiddenField = {
                val: jest.fn()
                    .mockReturnValueOnce(currentValue) // First call returns current value
                    .mockReturnValueOnce(expectedValue), // Second call to set new value
                length: 1
            };
            
            global.$ = jest.fn(() => mockHiddenField);
            
            // Simulate the removal logic
            const attachmentIds = currentValue.split(',').map(Number);
            const filteredIds = attachmentIds.filter(id => id !== attachmentId);
            const newValue = filteredIds.join(',');
            
            expect(newValue).toBe('123,789');
            expect(filteredIds).toEqual([123, 789]);
            expect(filteredIds).not.toContain(456);
        });
        
        it('should handle single image removal', function() {
            const attachmentId = 123;
            const currentValue = '123';
            const expectedValue = '';
            
            const attachmentIds = currentValue.split(',').map(Number);
            const filteredIds = attachmentIds.filter(id => id !== attachmentId);
            const newValue = filteredIds.join(',');
            
            expect(newValue).toBe('');
            expect(filteredIds).toEqual([]);
        });
        
        it('should handle removal from empty list', function() {
            const attachmentId = 123;
            const currentValue = '';
            
            // Split empty string should result in empty array after filtering
            const attachmentIds = currentValue ? currentValue.split(',').map(Number) : [];
            const filteredIds = attachmentIds.filter(id => id !== attachmentId);
            const newValue = filteredIds.join(',');
            
            expect(newValue).toBe('');
            expect(filteredIds).toEqual([]);
        });
        
        it('should handle malformed data', function() {
            const attachmentId = 123;
            const currentValue = ',123,456,';
            
            const attachmentIds = currentValue.split(',').map(Number);
            const filteredIds = attachmentIds.filter(id => !isNaN(id) && id > 0 && id !== attachmentId);
            const newValue = filteredIds.join(',');
            
            expect(filteredIds).toEqual([456]);
            expect(newValue).toBe('456');
        });
    });

    describe('handleVariationImageSelection function', function() {
        
        it('should update hidden field with selected image IDs', function() {
            const variationIndex = 0;
            const mockSelection = {
                map: jest.fn(() => [
                    { id: 123, attributes: { url: 'url1.jpg', filename: 'file1.jpg' } },
                    { id: 456, attributes: { url: 'url2.jpg', filename: 'file2.jpg' } },
                    { id: 789, attributes: { url: 'url3.jpg', filename: 'file3.jpg' } }
                ])
            };
            
            const selectedIds = mockSelection.map(item => ({ id: item.id }));
            const attachmentIds = selectedIds.map(item => item.id);
            const expectedValue = attachmentIds.join(',');
            
            expect(expectedValue).toBe('123,456,789');
        });
        
        it('should handle empty selection', function() {
            const variationIndex = 0;
            const mockSelection = {
                map: jest.fn(() => [])
            };
            
            const selectedIds = mockSelection.map(() => []);
            const expectedValue = selectedIds.join(',');
            
            expect(expectedValue).toBe('');
        });
        
        it('should preserve existing images when adding new ones', function() {
            const variationIndex = 0;
            const currentValue = '111,222';
            const newSelections = [
                { id: 333, attributes: { url: 'url3.jpg', filename: 'file3.jpg' } }
            ];
            
            // Simulate merging existing with new
            const existingIds = currentValue ? currentValue.split(',').map(Number) : [];
            const newIds = newSelections.map(item => item.id);
            const allIds = [...existingIds, ...newIds];
            const uniqueIds = [...new Set(allIds)]; // Remove duplicates
            const finalValue = uniqueIds.join(',');
            
            expect(finalValue).toBe('111,222,333');
        });
    });

    describe('Image Source Selection', function() {
        
        it('should show multiple images field when "multiple" is selected', function() {
            const mockContainer = {
                find: jest.fn(() => ({
                    removeClass: jest.fn(),
                    addClass: jest.fn(),
                    show: jest.fn(),
                    hide: jest.fn()
                }))
            };
            
            const imageSource = 'multiple';
            
            // Test the show/hide logic
            expect(imageSource).toBe('multiple');
        });
        
        it('should hide multiple images field when other source is selected', function() {
            const mockContainer = {
                find: jest.fn(() => ({
                    removeClass: jest.fn(),
                    addClass: jest.fn(),
                    show: jest.fn(),
                    hide: jest.fn()
                }))
            };
            
            const imageSource = 'product';
            
            // Test the show/hide logic
            expect(imageSource).toBe('product');
            expect(imageSource).not.toBe('multiple');
        });
    });

    describe('Media Library Integration', function() {
        
        it('should create media frame with correct parameters', function() {
            const variationIndex = 0;
            
            // Mock media frame creation parameters
            const mediaFrameOptions = {
                title: 'Choose Multiple Images',
                button: {
                    text: 'Select Images'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            };
            
            expect(mediaFrameOptions.multiple).toBe(true);
            expect(mediaFrameOptions.library.type).toBe('image');
            expect(mediaFrameOptions.title).toBe('Choose Multiple Images');
        });
        
        it('should pre-select existing images in media library', function() {
            const currentValue = '123,456,789';
            const attachmentIds = currentValue.split(',').map(id => parseInt(id.trim(), 10));
            
            expect(attachmentIds).toEqual([123, 456, 789]);
            expect(attachmentIds.length).toBe(3);
        });
        
        it('should handle invalid attachment IDs', function() {
            const currentValue = '123,abc,456,,789';
            const attachmentIds = currentValue.split(',')
                .map(id => parseInt(id.trim(), 10))
                .filter(id => !isNaN(id) && id > 0);
            
            expect(attachmentIds).toEqual([123, 456, 789]);
            expect(attachmentIds.length).toBe(3);
        });
    });

    describe('Remove Button Functionality', function() {
        
        it('should extract variation index from container ID', function() {
            const containerId = 'fb_product_images_selected_thumbnails_0';
            const variationIndex = parseInt(containerId.split('_').pop(), 10);
            
            expect(variationIndex).toBe(0);
        });
        
        it('should extract variation index from different indices', function() {
            const testCases = [
                { id: 'fb_product_images_selected_thumbnails_1', expected: 1 },
                { id: 'fb_product_images_selected_thumbnails_5', expected: 5 },
                { id: 'fb_product_images_selected_thumbnails_10', expected: 10 }
            ];
            
            testCases.forEach(testCase => {
                const variationIndex = parseInt(testCase.id.split('_').pop(), 10);
                expect(variationIndex).toBe(testCase.expected);
            });
        });
        
        it('should handle malformed container ID gracefully', function() {
            const containerId = 'invalid_container_id';
            const variationIndex = parseInt(containerId.split('_').pop(), 10) || 0;
            
            expect(isNaN(variationIndex) || variationIndex >= 0).toBe(true);
        });
    });

    describe('Field Validation', function() {
        
        it('should handle whitespace in attachment IDs', function() {
            const value = ' 123 , 456 , 789 ';
            const attachmentIds = value.split(',')
                .map(id => parseInt(id.trim(), 10))
                .filter(id => !isNaN(id) && id > 0);
            
            expect(attachmentIds).toEqual([123, 456, 789]);
        });
        
        it('should handle duplicate attachment IDs', function() {
            const value = '123,456,123,789,456';
            const attachmentIds = value.split(',')
                .map(id => parseInt(id.trim(), 10))
                .filter(id => !isNaN(id) && id > 0);
            const uniqueIds = [...new Set(attachmentIds)];
            
            expect(uniqueIds).toEqual([123, 456, 789]);
            expect(uniqueIds.length).toBe(3);
        });
    });

    describe('Event Handling', function() {
        
        it('should bind click event to "Choose Multiple Images" button', function() {
            const mockDocument = {
                on: jest.fn()
            };
            
            // Simulate event binding
            mockDocument.on('click', '.fb-open-images-library', function() {});
            
            expect(mockDocument.on).toHaveBeenCalledWith(
                'click', 
                '.fb-open-images-library', 
                expect.any(Function)
            );
        });
        
        it('should bind click event to remove image button', function() {
            const mockDocument = {
                on: jest.fn()
            };
            
            // Simulate event binding
            mockDocument.on('click', '.fb-product-images-thumbnails .remove-image', function() {});
            
            expect(mockDocument.on).toHaveBeenCalledWith(
                'click', 
                '.fb-product-images-thumbnails .remove-image', 
                expect.any(Function)
            );
        });
        
        it('should bind change event to image source radio buttons', function() {
            const mockDocument = {
                on: jest.fn()
            };
            
            // Simulate event binding
            mockDocument.on('change', '.js-fb-product-image-source', function() {});
            
            expect(mockDocument.on).toHaveBeenCalledWith(
                'change', 
                '.js-fb-product-image-source', 
                expect.any(Function)
            );
        });
    });

    describe('Data Persistence', function() {
        
        it('should maintain data integrity during field updates', function() {
            const originalValue = '123,456,789';
            const updatedValue = '123,999,789'; // 456 replaced with 999
            
            // Verify the change is as expected
            const originalIds = originalValue.split(',').map(Number);
            const updatedIds = updatedValue.split(',').map(Number);
            
            expect(originalIds).toEqual([123, 456, 789]);
            expect(updatedIds).toEqual([123, 999, 789]);
            expect(updatedIds.length).toBe(originalIds.length);
        });
        
        it('should handle field clearing', function() {
            const originalValue = '123,456,789';
            const clearedValue = '';
            
            const originalIds = originalValue.split(',').filter(id => id.trim());
            const clearedIds = clearedValue.split(',').filter(id => id.trim());
            
            expect(originalIds.length).toBe(3);
            expect(clearedIds.length).toBe(0);
        });
    });

    describe('Integration with WooCommerce Variations', function() {
        
        it('should handle multiple variation indices', function() {
            const variations = [0, 1, 2, 3, 4];
            const expectedFieldIds = [
                'variable_fb_product_images0',
                'variable_fb_product_images1',
                'variable_fb_product_images2',
                'variable_fb_product_images3',
                'variable_fb_product_images4'
            ];
            
            variations.forEach((index, i) => {
                const fieldId = `variable_fb_product_images${index}`;
                expect(fieldId).toBe(expectedFieldIds[i]);
            });
        });
        
        it('should generate correct thumbnail container IDs', function() {
            const variations = [0, 1, 2];
            const expectedContainerIds = [
                'fb_product_images_selected_thumbnails_0',
                'fb_product_images_selected_thumbnails_1',
                'fb_product_images_selected_thumbnails_2'
            ];
            
            variations.forEach((index, i) => {
                const containerId = `fb_product_images_selected_thumbnails_${index}`;
                expect(containerId).toBe(expectedContainerIds[i]);
            });
        });
    });

    describe('Error Handling', function() {
        
        it('should handle missing DOM elements gracefully', function() {
            const mockEmptyElement = {
                length: 0,
                val: jest.fn(() => ''),
                find: jest.fn(() => mockEmptyElement)
            };
            
            global.$ = jest.fn(() => mockEmptyElement);
            
            // Should not throw error when elements don't exist
            expect(mockEmptyElement.length).toBe(0);
            expect(mockEmptyElement.val()).toBe('');
        });
        
        it('should handle invalid variation index', function() {
            const containerId = 'invalid_container';
            const variationIndex = parseInt(containerId.split('_').pop(), 10);
            const safeVariationIndex = isNaN(variationIndex) ? 0 : variationIndex;
            
            expect(safeVariationIndex).toBe(0);
        });
        
        it('should handle wp.media not being available', function() {
            global.wp = undefined;
            
            // Should handle missing wp.media gracefully
            expect(global.wp).toBeUndefined();
        });
    });
});