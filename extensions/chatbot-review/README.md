# Support

Telegram: https://t.me/heew_support

## Modern runtime upgrade

This release preserves the existing extension identity and compatibility APIs while adding versioned migrations, runtime services, health checks, logging, metrics, REST-ready services, webhook/event foundations, permissions/feature-flag support, safe data-preserving uninstall defaults, and automated metadata tests.

### Upgrade safety

Run the normal MagicAI extension migration workflow. Existing tables and columns are preserved; new migrations use additive changes and guarded table/column creation. Uninstall behavior defaults to preserving operational data.

### Health and operations

The extension includes `config/platform-quality.php`, structured extension logging, metric events, and a health service or shared runtime health endpoint where applicable.
