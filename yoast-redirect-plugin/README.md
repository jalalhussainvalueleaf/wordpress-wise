# 🔄 Yoast Redirect API Plugin

A WordPress plugin that provides REST API endpoints to check Yoast SEO Premium redirects by slug.

## 📁 Files in Your Plugin Directory

- `yoast-redirect-api.php` - Core API functionality
- `yoast-redirect-api-plugin.php` - Main plugin file with admin interface
- `README.md` - This documentation

## 🚀 Installation & Setup

1. **Plugin is already in your directory:**
   ```
   /wp-content/plugins/yoast-redirect-plugin/
   ```

2. **Activate the plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Yoast Redirect API" and click "Activate"

3. **Test the API:**
   - Visit: `http://yoursite.com/wp-admin/options-general.php?page=yoast-redirect-api`
   - Or test directly: `http://yoursite.com/wp-json/yoast-redirects/v1/stats`

## 📡 API Endpoints

### 1. Check Single Redirect
```
GET /wp-json/yoast-redirects/v1/check?slug=/your-slug
```

**Example:**
```bash
curl "http://yoursite.com/wp-json/yoast-redirects/v1/check?slug=/old-page"
```

**Response:**
```json
{
  "found": true,
  "redirect_to": "/new-page",
  "status": 301,
  "origin": "/old-page",
  "source": "wpseo-premium-redirects-base"
}
```

### 2. Bulk Check Multiple Slugs
```
POST /wp-json/yoast-redirects/v1/bulk-check
Content-Type: application/json

{
  "slugs": ["/old-page1", "/old-page2", "/old-page3"]
}
```

**Example:**
```bash
curl -X POST "http://yoursite.com/wp-json/yoast-redirects/v1/bulk-check" \
  -H "Content-Type: application/json" \
  -d '{"slugs": ["/page1", "/page2", "/page3"]}'
```

**Response:**
```json
{
  "total_checked": 3,
  "results": [
    {
      "found": true,
      "redirect_to": "/new-page1",
      "status": 301
    },
    {
      "found": false,
      "redirect_to": null,
      "status": 200
    }
  ]
}
```

### 3. Get All Redirects
```
GET /wp-json/yoast-redirects/v1/all
```

### 4. Get Statistics
```
GET /wp-json/yoast-redirects/v1/stats
```

## 🔍 How It Works

The plugin reads redirect data from Yoast SEO Premium's actual storage locations in the WordPress options table:

- `wpseo-premium-redirects-base` (main redirects since v3.1)
- `wpseo-premium-redirects-export-plain` (plain text redirects)
- `wpseo-premium-redirects-export-regex` (regex redirects)
- `wpseo-premium-redirects` (legacy plain redirects)
- `wpseo-premium-redirects-regex` (legacy regex redirects)

## 🧪 Testing

### Admin Interface
1. Go to **Settings → Yoast Redirect API** in WordPress admin
2. Click **Test API** button to verify it's working
3. View API endpoints and documentation

### Command Line Testing
```bash
# Test single redirect
curl "http://yoursite.com/wp-json/yoast-redirects/v1/check?slug=/test-page"

# Test stats
curl "http://yoursite.com/wp-json/yoast-redirects/v1/stats"

# Test bulk check
curl -X POST "http://yoursite.com/wp-json/yoast-redirects/v1/bulk-check" \
  -H "Content-Type: application/json" \
  -d '{"slugs": ["/page1", "/page2"]}'
```

### JavaScript Example
```javascript
// Check single redirect
fetch('/wp-json/yoast-redirects/v1/check?slug=/old-page')
  .then(response => response.json())
  .then(data => console.log(data));

// Bulk check
fetch('/wp-json/yoast-redirects/v1/bulk-check', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    slugs: ['/page1', '/page2', '/page3']
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## 📊 Response Formats

### Success Response (Found Redirect)
```json
{
  "found": true,
  "redirect_to": "/new-page",
  "status": 301,
  "origin": "/old-page",
  "source": "wpseo-premium-redirects-base"
}
```

### Not Found Response
```json
{
  "found": false,
  "redirect_to": null,
  "status": 200,
  "message": "No redirect found for slug: /old-page"
}
```

### Statistics Response
```json
{
  "total": 25,
  "sources": {
    "wpseo-premium-redirects-base": 25,
    "wpseo-premium-redirects-export-plain": 0,
    "wpseo-premium-redirects-export-regex": 0,
    "wpseo-premium-redirects": 0,
    "wpseo-premium-redirects-regex": 0
  }
}
```

## 🔐 Security & Permissions

- ✅ **Public Access**: No authentication required
- ✅ **Input Sanitization**: All inputs are sanitized
- ✅ **Safe Database Access**: Uses WordPress options API
- ✅ **Error Handling**: Proper error responses without data leaks

## 🎯 Perfect for Your Use Case

This plugin gives you exactly what you need:

- ✅ **Slug-based lookup** - no pagination required
- ✅ **Fast response** - direct array search
- ✅ **Accurate data** - reads from Yoast's actual storage
- ✅ **Multiple endpoints** - single, bulk, and stats
- ✅ **Admin interface** - easy testing and management

## 🚨 Requirements

- **WordPress 4.7+**
- **Yoast SEO Premium** (must have redirect data to return results)
- **PHP 5.6+**

## 🐛 Troubleshooting

### No Redirects Found
- Ensure Yoast SEO Premium is installed and activated
- Check if redirects exist in Yoast SEO → Redirects
- Verify the plugin is activated

### 404 Error on API
- Check if plugin is activated
- Ensure WordPress permalinks are enabled
- Try accessing the admin page first

### Empty Responses
- The API reads from WordPress options, not database tables
- Make sure Yoast has created some redirects first

## 📝 Support

The plugin reads directly from Yoast SEO Premium's storage, so it will work as long as:
1. Yoast SEO Premium is installed
2. Redirects have been created in Yoast
3. The plugin is activated

Your API is now ready to use! 🎉
