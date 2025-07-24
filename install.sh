#!/bin/bash

# Composer Authenticated Repository Plugin Installation Script

set -e

echo "🚀 Installing Composer Authenticated Repository Plugin..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if we're in a composer project
if [ ! -f "composer.json" ]; then
    echo "❌ No composer.json found. Please run this script from a Composer project directory."
    exit 1
fi

# Install the plugin
echo "📦 Installing plugin dependencies..."
composer require sonrac/composer-authenticated-repository-plugin

# Add plugin to allow-plugins config if not already present
if ! grep -q "sonrac/composer-authenticated-repository-plugin" composer.json; then
    echo "⚙️  Adding plugin to allow-plugins configuration..."
    
    # Create a temporary file with the updated composer.json
    jq '.config.allow_plugins["sonrac/composer-authenticated-repository-plugin"] = true' composer.json > composer.json.tmp
    mv composer.json.tmp composer.json
    
    echo "✅ Plugin added to allow-plugins configuration"
fi

echo ""
echo "🎉 Installation complete!"
echo ""
echo "📝 Next steps:"
echo "1. Configure authentication in Composer:"
echo "   # For GitHub tokens:"
echo "   composer config github-oauth.api.github.com YOUR_GITHUB_TOKEN"
echo ""
echo "   # For HTTP basic auth:"
echo "   composer config http-basic.your-repo.com username password"
echo ""
echo "2. Add authenticated repositories to your composer.json:"
echo "   {"
echo "     \"repositories\": ["
echo "       {"
echo "         \"type\": \"composer-authenticated\","
echo "         \"url\": \"https://your-repo.com/composer-repository.json\""
echo "       }"
echo "     ]"
echo "   }"
echo ""
echo "3. Run 'composer update' to test the plugin"
echo ""
echo "📚 For more information, see the README.md file" 
