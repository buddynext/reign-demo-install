# Reign Theme Demo Installer System - Complete Roadmap

## Project Overview
A centralized demo distribution system for 40 Reign Theme demos, featuring automated export/import functionality with a central hub that links to individual demo JSON files.

## System Architecture

### 1. Central Hub Structure (installer.wbcomdesigns.com)
```
installer.wbcomdesigns.com/
├── reign-demos/                     # Root folder for central hub
│   ├── master-registry.json        # Master registry linking all demos
│   ├── demo-assets/                # Shared assets
│   │   ├── previews/              # Demo preview images
│   │   └── icons/                 # Category icons
│   └── plugins/                    # Self-hosted plugins repository
│       ├── custom-plugin-1.zip
│       ├── custom-plugin-2.zip
│       └── ...
```

### 2. Individual Demo Sites Structure
Each of the 40 demo sites will have:
```
demo-site.com/
├── reign-demo-export/              # Created by exporter
│   ├── manifest.json              # Complete demo manifest
│   ├── files-manifest.json        # Files listing
│   ├── plugins-manifest.json      # Plugins listing
│   └── content-package.zip        # Exportable content
```

## Master Registry JSON Structure

### master-registry.json (Central Hub)
```json
{
  "plugin_name": "Reign Demo",
  "version": "1.0.0",
  "theme": "Reign Theme",
  "last_updated": "2025-01-03T00:00:00Z",
  "demos_count": 40,
  "demos": [
    {
      "id": "reign-business-01",
      "name": "Business Pro",
      "slug": "business-pro",
      "category": "business",
      "version": "1.0.0",
      "demo_url": "https://business.reigndemos.com",
      "preview_image": "https://installer.wbcomdesigns.com/reign-demos/demo-assets/previews/business-pro.jpg",
      "description": "Professional business website with Reign Theme",
      "features": ["BuddyPress", "WooCommerce", "Elementor", "LearnDash"],
      "manifest_url": "https://business.reigndemos.com/reign-demo-export/manifest.json",
      "files_manifest_url": "https://business.reigndemos.com/reign-demo-export/files-manifest.json",
      "plugins_manifest_url": "https://business.reigndemos.com/reign-demo-export/plugins-manifest.json",
      "package_url": "https://business.reigndemos.com/reign-demo-export/content-package.zip",
      "requirements": {
        "wp_version": "6.0+",
        "php_version": "7.4+",
        "memory_limit": "256M"
      },
      "tags": ["business", "corporate", "professional", "buddypress"]
    },
    {
      "id": "reign-community-01",
      "name": "Community Hub",
      "slug": "community-hub",
      "category": "community",
      "version": "1.0.0",
      "demo_url": "https://community.reigndemos.com",
      "preview_image": "https://installer.wbcomdesigns.com/reign-demos/demo-assets/previews/community-hub.jpg",
      "description": "Social community platform with Reign Theme",
      "features": ["BuddyPress", "bbPress", "Activity Feeds", "Groups"],
      "manifest_url": "https://community.reigndemos.com/reign-demo-export/manifest.json",
      "files_manifest_url": "https://community.reigndemos.com/reign-demo-export/files-manifest.json",
      "plugins_manifest_url": "https://community.reigndemos.com/reign-demo-export/plugins-manifest.json",
      "package_url": "https://community.reigndemos.com/reign-demo-export/content-package.zip",
      "requirements": {
        "wp_version": "6.0+",
        "php_version": "7.4+",
        "memory_limit": "256M"
      },
      "tags": ["community", "social", "buddypress", "forums"]
    }
    // ... 38 more demos
  ],
  "categories": [
    {
      "id": "business",
      "name": "Business",
      "count": 8
    },
    {
      "id": "community",
      "name": "Community",
      "count": 10
    },
    {
      "id": "education",
      "name": "Education",
      "count": 6
    },
    {
      "id": "marketplace",
      "name": "Marketplace",
      "count": 5
    },
    {
      "id": "membership",
      "name": "Membership",
      "count": 6
    },
    {
      "id": "portfolio",
      "name": "Portfolio",
      "count": 5
    }
  ],
  "self_hosted_plugins": {
    "repository_url": "https://installer.wbcomdesigns.com/reign-demos/plugins/",
    "plugins": [
      {
        "slug": "reign-custom-addon",
        "name": "Reign Custom Addon",
        "version": "1.0.0",
        "download_url": "https://installer.wbcomdesigns.com/reign-demos/plugins/reign-custom-addon.zip"
      }
    ]
  }
}
```

## Individual Demo Site Manifests

### manifest.json (On Each Demo Site)
```json
{
  "demo_id": "reign-business-01",
  "demo_name": "Business Pro",
  "theme": "Reign Theme",
  "theme_version": "8.0.0",
  "export_version": "1.0.0",
  "export_date": "2025-01-03T00:00:00Z",
  "wordpress_version": "6.4.2",
  "site_url": "https://business.reigndemos.com",
  "content_summary": {
    "posts": 25,
    "pages": 15,
    "media_items": 180,
    "menus": 4,
    "widgets": 12,
    "users": 5,
    "buddypress_groups": 8,
    "buddypress_activities": 50,
    "woocommerce_products": 30,
    "custom_post_types": {
      "portfolio": 12,
      "testimonials": 8
    }
  },
  "theme_settings": {
    "customizer_settings": true,
    "theme_options": true,
    "reign_settings": true
  }
}
```

### plugins-manifest.json (On Each Demo Site)
```json
{
  "demo_id": "reign-business-01",
  "plugins_count": 15,
  "plugins": {
    "required": [
      {
        "name": "BuddyPress",
        "slug": "buddypress",
        "version": "12.0.0",
        "source": "wordpress.org",
        "type": "free",
        "status": "active",
        "required": true
      },
      {
        "name": "WooCommerce",
        "slug": "woocommerce",
        "version": "8.4.0",
        "source": "wordpress.org",
        "type": "free",
        "status": "active",
        "required": true
      },
      {
        "name": "Elementor",
        "slug": "elementor",
        "version": "3.18.0",
        "source": "wordpress.org",
        "type": "free",
        "status": "active",
        "required": true
      },
      {
        "name": "Elementor Pro",
        "slug": "elementor-pro",
        "version": "3.18.0",
        "source": "purchase",
        "type": "premium",
        "purchase_url": "https://elementor.com/pro/",
        "license_required": true,
        "status": "active",
        "required": true
      },
      {
        "name": "Reign Theme Addon",
        "slug": "reign-theme-addon",
        "version": "2.0.0",
        "source": "self-hosted",
        "type": "custom",
        "download_url": "https://installer.wbcomdesigns.com/reign-demos/plugins/reign-theme-addon.zip",
        "status": "active",
        "required": true
      }
    ],
    "optional": [
      {
        "name": "Yoast SEO",
        "slug": "wordpress-seo",
        "version": "21.7",
        "source": "wordpress.org",
        "type": "free",
        "status": "active",
        "required": false
      }
    ]
  }
}
```

### files-manifest.json (On Each Demo Site)
```json
{
  "demo_id": "reign-business-01",
  "export_size": "125MB",
  "files": {
    "uploads": {
      "total_size": "98MB",
      "directories": [
        {
          "path": "2024/",
          "size": "45MB",
          "file_count": 156
        },
        {
          "path": "2025/",
          "size": "53MB",
          "file_count": 89
        }
      ],
      "special_folders": [
        "buddypress/",
        "woocommerce_uploads/",
        "elementor/css/"
      ]
    },
    "theme_files": {
      "custom_css": true,
      "child_theme": false,
      "additional_files": [
        "fonts/",
        "custom-templates/"
      ]
    },
    "database_tables": {
      "custom_tables": [
        "reign_notifications",
        "reign_user_settings"
      ]
    }
  }
}
```

## Plugin Development

### 1. Reign Demo Exporter (For Demo Sites)

**Plugin Name:** Reign Demo Exporter
**Description:** Export tool for Reign Theme demo sites

#### Core Features:
- Generate JSON manifests (manifest.json, plugins-manifest.json, files-manifest.json)
- Export content to packaged format
- Upload manifests to accessible URLs
- Maintain export history
- Selective content export

#### Admin Interface Structure:
```
WordPress Admin → Tools → Reign Demo Export
├── Export Overview
├── Content Selection
├── Plugin Analysis
├── File Scanner
├── Generate Manifests
└── Export History
```

#### Key Functions:
```php
// Core export functions
reign_demo_analyze_plugins()         // Scan and categorize plugins
reign_demo_scan_files()             // Create files inventory
reign_demo_export_content()         // Export selected content
reign_demo_generate_manifests()     // Create JSON files
reign_demo_create_package()         // Package content
reign_demo_publish_manifests()      // Make JSONs accessible
```

### 2. Reign Demo Importer (For Fresh WP Sites)

**Plugin Name:** Reign Demo Importer
**Description:** One-click demo installer for Reign Theme

#### Core Features:
- Connect to central hub
- Browse 40 Reign demos
- Real-time requirements check
- Progressive installation
- Plugin auto-installer
- Content mapping
- Success/error reporting

#### Installation Flow:
1. **Demo Selection**
   - Fetch master registry from central hub
   - Display demos with previews
   - Filter by category/features

2. **Pre-Installation Check**
   - Verify system requirements
   - Check Reign Theme activation
   - Analyze current plugins

3. **Plugin Installation**
   - Read plugins-manifest.json from demo site
   - Install free plugins from WordPress.org
   - Prompt for premium plugin licenses
   - Download self-hosted plugins

4. **Content Import**
   - Download content package
   - Import posts, pages, media
   - Configure menus and widgets
   - Apply theme settings

5. **Post-Installation**
   - Activate required plugins
   - Regenerate permalinks
   - Clear caches
   - Display success report

#### Admin Interface Structure:
```
WordPress Admin → Reign → Demo Importer
├── Demo Browser
│   ├── Grid View
│   ├── List View
│   └── Category Filter
├── Installation Wizard
│   ├── Requirements Check
│   ├── Plugin Installation
│   ├── Content Import
│   └── Final Setup
└── Import History
```

## Implementation Strategy

### Phase 1: Infrastructure Setup
- Create central hub directory structure
- Set up master registry JSON
- Configure CORS for cross-domain access
- Implement security measures

### Phase 2: Exporter Plugin Development
- Build core export functionality
- Create manifest generators
- Develop admin interface
- Add validation and error handling

### Phase 3: Demo Site Configuration
- Install Reign Demo Exporter on all 40 sites
- Configure export settings
- Generate initial manifests
- Test accessibility of JSON files

### Phase 4: Importer Plugin Development
- Build connection to central hub
- Implement demo browser
- Create installation wizard
- Add progress tracking

### Phase 5: Integration Testing
- Test complete workflow
- Verify all 40 demos
- Performance optimization
- Security audit

### Phase 6: Documentation & Launch
- User documentation
- Video tutorials
- Troubleshooting guide
- Launch preparation

## Technical Specifications

### Central Hub Requirements
- PHP 7.4+
- HTTPS enabled
- CORS configuration
- CDN recommended

### JSON File Access
- All JSON files must be publicly accessible
- Proper MIME types configured
- Cross-origin headers set

### Security Measures
- Manifest validation
- Package integrity checks
- Secure file downloads
- Input sanitization

### Performance Optimization
- Chunked imports for large sites
- Background processing
- Progress indicators
- Resume capability

## Maintenance Guidelines

### Regular Updates
- Update demo content
- Refresh manifests
- Plugin compatibility checks
- Security patches

### Monitoring
- Track successful imports
- Log common errors
- Monitor download speeds
- User feedback collection

### Support Resources
- Installation guides
- Video walkthroughs
- FAQ documentation
- Support ticket system

## Success Metrics
- One-click installation success rate
- Average import completion time
- Plugin compatibility rate
- User satisfaction score
- Support ticket volume

## Future Enhancements
1. Incremental demo updates
2. Custom demo builder
3. White-label support
4. Multisite compatibility
5. CLI support
6. Demo variations
7. A/B testing features
8. Analytics integration