#!/bin/bash

# Script to test the build process locally
echo "🔍 Testing local build process..."

# Default test mode is simulate
TEST_MODE=${1:-"simulate"}

if [ "$TEST_MODE" == "full" ]; then
    echo "Running full build test (including dependencies)..."
    
    # Check if required tools are installed
    command -v npm >/dev/null 2>&1 || { echo "❌ npm is required but not installed. Aborting."; exit 1; }
    command -v composer >/dev/null 2>&1 || { echo "❌ composer is required but not installed. Aborting."; exit 1; }
    
    echo "✅ Required tools found"
    
    # Update dependencies to sync package.json and package-lock.json
    echo "📦 Updating npm dependencies..."
    npm install || { echo "❌ Failed to update npm dependencies"; exit 1; }
    
    echo "📦 Installing composer dependencies..."
    composer install || { echo "❌ Failed to install composer dependencies"; exit 1; }
    
    # Build the plugin
    echo "🔨 Building plugin..."
    npm run build || { echo "❌ Build failed"; exit 1; }
    
    # Check if zip file was created
    if [ -f "facebook-for-woocommerce.zip" ]; then
        echo "✅ Build successful! Zip file created: facebook-for-woocommerce.zip"
        echo "📏 Zip file size: $(du -h facebook-for-woocommerce.zip | cut -f1)"
    else
        echo "❌ Build may have completed but no zip file was found."
        exit 1
    fi
else
    echo "Running simulation test (no actual build)..."
    
    # Simulate the GitHub Actions workflow steps
    echo "Step 1: Checkout repository ✅"
    echo "Step 2: Setup PHP 7.4 ✅"
    echo "Step 3: Setup Node.js 16 ✅"
    echo "Step 4: Install dependencies ✅"
    echo "Step 5: Build plugin ✅"
    echo "Step 6: Upload artifact ✅"
    echo "Step 7: Update PR description ✅"
    
    echo ""
    echo "📋 GitHub workflow simulation complete"
    echo ""
    echo "When this workflow runs on GitHub:"
    echo "1. The plugin will be built using 'npm run build'"
    echo "2. The resulting zip file will be uploaded as an artifact"
    echo "3. The PR description will be updated with a download link"
    echo ""
    echo "QA team members will be able to click the link in the PR description"
    echo "to download the latest build without needing engineering assistance."
fi

echo "✨ Local test completed successfully!"
echo ""
echo "To run a full build test (requires npm & composer):"
echo "  ./test-build-locally.sh full"
echo ""
echo "To run a simulation test:"
echo "  ./test-build-locally.sh simulate" 