# Elementor Content Processing Guide

This document captures key patterns and information for developing Elementor widget support in the Smartling Connector plugin.

## Architecture Overview

### Version Support

The plugin supports two major Elementor format versions through separate but related handlers:

| Class | Factory | Version | Format |
|-------|---------|---------|--------|
| `ExternalContentElementor3` | `ElementFactory3` | 3.x | Flat string settings |
| `ExternalContentElementor4` | `ElementFactory4` | 4.x | `$$type`-annotated settings |

Both extend `ExternalContentElementorAbstract`, which contains all shared logic (upload/download pipeline, meta field management, conditions field handling). Version detection uses `getMinVersion()`/`getMaxVersion()` from the parent `ExternalContentAbstract`.
Changes to Elementor 3 related files are discouraged, the support should continue for the latest version.

### Element Class Hierarchy

**Elementor 3 (flat settings)**
- `ElementAbstract` — Base class for all elements
- `Unknown` — Default handler; passes child elements through recursively
- `Elements/` — Specific widget handlers (Gallery, Tabs, LoopCarousel, etc.)

**Elementor 4 (`$$type` settings)**
- `ElementAbstract4` — Extends `ElementAbstract`; adds `$$type`-aware extraction and write-back
- `Elements4/` — Specific widget handlers for `e-*` widget types (EHeading, EParagraph, EButton, EImage, EFormLabel, EFormInput, EFormTextarea)

`ElementFactory4` loads both `Elements/` and `Elements4/` so that an Elementor 4 page containing old-format widgets (e.g., `container`, `gallery`, `blockquote`) falls through to the existing v3 handlers automatically.

### Shared Interface

`ExternalContentElementorInterface` is implemented by both `ExternalContentElementor3` and `ExternalContentElementor4`. Element handlers receive this interface in `setRelations()` and `setTargetContent()`, decoupling them from a specific handler version.

```php
interface ExternalContentElementorInterface {
    public function getWpProxy(): WordpressFunctionProxyHelper;
    public function getTargetId(int $sourceBlogId, int $sourceId, int $targetBlogId, string $contentType = ...): ?int;
}
```

### Key Methods

#### `getRelated(): RelatedContentInfo`
Identifies related content (posts, attachments, taxonomy terms) that need translation. Always call `parent::getRelated()` first to inherit base functionality.

#### `getTranslatableStrings(): array`
Returns translatable text strings from the widget settings.

#### `setRelations()`
Replaces source content IDs with target (translated) content IDs during download. This method automatically handles content type detection:
- For `CONTENT_TYPE_POST`: Calls `get_post_type()` to determine the actual post type
- For `CONTENT_TYPE_TAXONOMY`: Calls `get_term()` to determine the actual taxonomy name (e.g., `product_tag`, `category`)
- For specific types (e.g., `attachment`, `product_tag`): Uses the type as-is

## Elementor 4 `$$type` Format

Elementor 4 stores settings as typed objects rather than flat values:

```json
{
  "title": {
    "$$type": "html-v3",
    "value": {
      "content": { "$$type": "string", "value": "Hello World" },
      "children": []
    }
  },
  "placeholder": { "$$type": "string", "value": "your@email.com" },
  "image": {
    "$$type": "image",
    "value": {
      "src": {
        "$$type": "image-src",
        "value": {
          "id": { "$$type": "image-attachment-id", "value": 23 },
          "url": null
        }
      }
    }
  }
}
```

### Extraction Paths

| `$$type` | Translatable value path | Notes |
|----------|------------------------|-------|
| `string` | `value` | Plain string |
| `html-v3` | `value.content.value` | Rich text; only the leaf string is extracted |
| `image` | `value.src.value.id.value` | Integer attachment ID (related content, not string) |

`ElementAbstract4::extractTypedValue()` handles `string` and `html-v3` cases. `ElementAbstract4::setTypedSettingValue()` writes translations back to the correct path while preserving all sibling `$$type` keys.

### Flattened Key Format

After `getContentFields()` flattens the extracted strings, keys follow the pattern:

```
{containerId}/{widgetId}/{settingKey}
```

Example: `container1/heading1/title` → `"Hello World"`

## Common Patterns

### Pattern 1: Processing Single Related Content Items

Used when a widget references a single piece of content (e.g., template ID, featured image).

```php
public function getRelated(): RelatedContentInfo
{
    $return = parent::getRelated();
    $key = "template_id";
    $id = $this->getIntSettingByKey($key, $this->settings);
    if ($id !== null) {
        $return->addContent(
            new Content($id, ContentTypeHelper::CONTENT_TYPE_POST),
            $this->id,
            "settings/$key"
        );
    }
    return $return;
}
```

**Example:** `Elements/LoopCarousel.php` (template_id), `Elements/Template.php`

### Pattern 2: Processing Arrays of Related Content

Used when a widget contains multiple items (e.g., image gallery, term ID lists).

```php
public function getRelated(): RelatedContentInfo
{
    $return = parent::getRelated();
    foreach ($this->settings['wp_gallery'] ?? [] as $index => $listItem) {
        $key = "wp_gallery/$index/id";
        $id = $this->getIntSettingByKey($key, $this->settings);
        if ($id !== null) {
            $return->addContent(
                new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT),
                $this->id,
                "settings/$key"
            );
        }
    }
    return $return;
}
```

**Example:** `Elements/ImageGallery.php` (wp_gallery), `Elements/LoopCarousel.php` (post_query_include_term_ids)

### Pattern 3: Elementor 4 — Typed Translatable Strings

Extend `ElementAbstract4`. Override `getTranslatableStrings()` using `extractTypedValue()`:

```php
class EHeading extends ElementAbstract4
{
    public function getType(): string { return 'e-heading'; }

    public function getTranslatableStrings(): array
    {
        $strings = parent::getTranslatableStrings();
        $value = $this->extractTypedValue($this->settings['title'] ?? null);
        if ($value !== null) {
            $strings[$this->id]['title'] = $value;
        }
        return $strings;
    }
}
```

`setTargetContent()` is handled by `ElementAbstract4` — it calls `setTypedSettingValue()` for each key returned by `getTranslatableStrings()`, so subclasses only need to implement extraction.

### Pattern 4: Elementor 4 — Related Content (`$$type: image`)

```php
class EImage extends ElementAbstract4
{
    public function getType(): string { return 'e-image'; }

    public function getRelated(): RelatedContentInfo
    {
        $return = parent::getRelated();
        $id = $this->settings['image']['value']['src']['value']['id']['value'] ?? null;
        if (is_int($id) && $id > 0) {
            $return->addContent(
                new Content($id, ContentTypeHelper::POST_TYPE_ATTACHMENT),
                $this->id,
                'settings/image/value/src/value/id/value'
            );
        }
        return $return;
    }
}
```

## Content Types

### Available Content Type Constants

From `ContentTypeHelper`:
- `ContentTypeHelper::CONTENT_TYPE_POST` — For posts, pages, custom post types
- `ContentTypeHelper::CONTENT_TYPE_TAXONOMY` — For taxonomy terms (generic)
- `ContentTypeHelper::POST_TYPE_ATTACHMENT` — For media attachments

### Taxonomy Terms

When processing taxonomy terms (categories, tags, custom taxonomies):

**Recommended: Use generic taxonomy constant**
```php
new Content($termId, ContentTypeHelper::CONTENT_TYPE_TAXONOMY)
```

The `ElementAbstract::setRelations()` method automatically detects the actual taxonomy name by calling `get_term()`. This is preferred as it works for all taxonomy types without knowing them in advance.

## Widget Settings Structure

### Elementor 3 — LoopCarousel Widget Example

```json
{
  "template_id": 1690,
  "post_query_post_type": "product",
  "post_query_include": ["terms"],
  "post_query_include_term_ids": ["14", "15", "16"]
}
```

### Elementor 4 — Form Widget Example

```json
{
  "id": "form1",
  "elType": "e-form",
  "elements": [
    {
      "id": "input1",
      "elType": "widget",
      "widgetType": "e-form-input",
      "settings": {
        "placeholder": { "$$type": "string", "value": "First name" },
        "type": { "$$type": "string", "value": "text" },
        "_cssid": { "$$type": "string", "value": "e-form-first-name" }
      }
    }
  ]
}
```

### Common Settings Patterns

- Elementor 3: IDs are often stored as strings even when numeric — `"14"` not `14`
- Elementor 4: Every setting is wrapped in `{ "$$type": "...", "value": ... }`
- Arrays use numeric indices: `post_query_include_term_ids/0`
- Nested paths use forward slashes: `settings/wp_gallery/1/id`

## Testing Patterns

### Test 1: Verify Related Content Discovery

```php
public function testRelatedContent(): void
{
    $relatedList = (new MyWidget([
        'settings' => ['some_ids' => ['14', '15', '16']]
    ]))->getRelated()->getRelatedContentList();

    $this->assertArrayHasKey('expected_type', $relatedList);
    $this->assertEquals(['14', '15', '16'], $relatedList['expected_type']);
}
```

### Test 2: Verify Content Translation

Use `ExternalContentElementorInterface` (not `ExternalContentElementor3` or `ExternalContentElementor4`):

```php
public function testTranslation(): void
{
    $sourceId = 14;
    $targetId = 28;

    $externalContentElementor = $this->createMock(ExternalContentElementorInterface::class);
    $externalContentElementor->method('getTargetId')
        ->with(0, $sourceId, 0, 'content_type')->willReturn($targetId);

    $result = (new MyWidget([
        'settings' => ['some_id' => '14']
    ]))->setRelations(
        new Content($sourceId, 'content_type'),
        $externalContentElementor,
        'settings/some_id',
        $this->createMock(SubmissionEntity::class),
    );

    $this->assertEquals($targetId, $result->toArray()['settings']['some_id']);
}
```

### Test 3: Verify Elementor 4 String Extraction

```php
public function testExtractsHeadingTitle(): void
{
    $data = json_encode([[
        'id' => 'container1', 'elType' => 'e-flexbox', 'settings' => [], 'elements' => [[
            'id' => 'heading1', 'elType' => 'widget', 'widgetType' => 'e-heading',
            'settings' => [
                'title' => ['$$type' => 'html-v3', 'value' => [
                    'content' => ['$$type' => 'string', 'value' => 'Hello World'],
                    'children' => [],
                ]],
            ],
            'elements' => [], 'styles' => [], 'interactions' => [], 'editor_settings' => [], 'version' => '0.0',
        ]],
        'isInner' => false, 'styles' => [], 'interactions' => [], 'editor_settings' => [], 'version' => '0.0',
    ]]);

    $fields = $handler->getContentFields($submission, false);
    $this->assertEquals('Hello World', $fields['container1/heading1/title']);
}
```

## Development Workflow

### Adding Support for a New Elementor 3 Widget

1. **Create Element Class** in `inc/Smartling/ContentTypes/Elementor/Elements/`
   - Extend `Unknown`
   - Override `getType()` to return the widget type string
   - Override `getRelated()` if the widget has related content

2. **Identify Widget Structure**
   - Export Elementor page JSON and examine the widget's `settings` object
   - Look for ID fields: `*_id`, `*_ids`, nested arrays

3. **Implement `getRelated()`**
   - Call `parent::getRelated()` first
   - Loop through settings to find content references
   - Use `addContent()` with the correct content type and path

4. **Create Tests** in `tests/Smartling/ContentTypes/Elementor/`

### Adding Support for a New Elementor 4 Widget

1. **Create Element Class** in `inc/Smartling/ContentTypes/Elementor/Elements4/`
   - Extend `ElementAbstract4`
   - Override `getType()` to return the `e-*` widget type string

2. **For translatable text fields** — override `getTranslatableStrings()`:
   - Call `parent::getTranslatableStrings()` first
   - Use `extractTypedValue($this->settings['fieldKey'] ?? null)` for each field
   - Add to `$strings[$this->id]['fieldKey']` if non-null
   - `setTargetContent()` is inherited and handles write-back automatically

3. **For related content (attachment IDs)** — override `getRelated()`:
   - Call `parent::getRelated()` first
   - Navigate the `$$type` wrapper path to reach the integer ID
   - Use `addContent()` with `POST_TYPE_ATTACHMENT` and the full dotted path

4. **Non-translatable settings** — prefix the key with `_` or skip it; `EFormInput` excludes `type` and `_cssid` by only extracting `placeholder`

5. **Container types** (`e-flexbox`, `e-form`, `e-form-success-message`, etc.) have no own translatable settings and are handled automatically by the `Unknown` fallback, which recursively processes child elements. No handler needed.

6. **The factory picks up the new handler automatically** — `ElementFactory4` uses `DirectoryIterator` to load all `.php` files from `Elements4/`. No registration step required.

## Real-World Examples

### Case Study: LoopCarousel with Product Tags (WP-979)

**Problem:** LoopCarousel widgets filter products by tag IDs, but these IDs weren't being translated.

**Solution:** Added term ID processing to `LoopCarousel.php`:
```php
foreach ($this->settings['post_query_include_term_ids'] ?? [] as $index => $termId) {
    if (is_numeric($termId)) {
        $return->addContent(
            new Content((int)$termId, ContentTypeHelper::CONTENT_TYPE_TAXONOMY),
            $this->id,
            "settings/post_query_include_term_ids/$index"
        );
    }
}
```

**Key Learnings:**
- Term IDs are stored as strings in JSON: `["14", "15", "16"]`
- Check `is_numeric()` before casting to int
- Use array iteration with index to build proper paths
- Generic `CONTENT_TYPE_TAXONOMY` works for all taxonomy types

### Case Study: Elementor 4 Support (WP-1000)

**Problem:** Elementor 4 changed `_elementor_data` to wrap all settings in `$$type` objects. The existing Elementor 3 handler couldn't extract strings or write translations back.

**Solution:**
- `ExternalContentElementorAbstract` extracted as shared base for both v3 and v4 handlers
- `ElementFactory4` loads `Elements/` (v3 handlers) + `Elements4/` (v4-specific handlers), so mixed-format pages work correctly
- `ElementAbstract4` adds `extractTypedValue()` and `setTypedSettingValue()` for `$$type`-aware processing
- Seven `Elements4/` handlers cover the `e-*` widget types present in Elementor 4 pages

## Tips and Best Practices

1. **Always use null-coalescing operator** (`??`) when accessing settings arrays
2. **Build proper paths** — They must match the exact structure in settings JSON
3. **Use `getIntSettingByKey()`** — Handles nested path resolution and type casting (v3)
4. **Call parent methods** — Don't skip `parent::getRelated()` or `parent::getTranslatableStrings()`
5. **Check for numeric before casting** — Elementor 3 settings often store numbers as strings
6. **Write tests** — Test both discovery and translation of related content
7. **Use constants** — Prefer `ContentTypeHelper::CONTENT_TYPE_*` over magic strings
8. **Mock the interface, not the class** — Use `createMock(ExternalContentElementorInterface::class)` in tests

## Debugging Tips

### Common Issues
- **Path mismatch** — Ensure path in `addContent()` matches exact JSON structure
- **Type mismatch** — Check if IDs are stored as strings vs integers (v3) or integers vs `$$type` wrappers (v4)
- **Missing null checks** — Always use `??` for optional settings
- **Wrong content type** — Verify you're using the correct type constant
- **v4 write-back broken** — Check `setTypedSettingValue()` handles the correct `$$type` variant for the field

## Related Files

- `inc/Smartling/ContentTypes/Elementor/ExternalContentElementorInterface.php` — Shared interface for v3/v4 handlers
- `inc/Smartling/ContentTypes/ExternalContentElementorAbstract.php` — Shared handler logic (upload/download pipeline)
- `inc/Smartling/ContentTypes/ExternalContentElementor3.php` — Elementor 3.x handler
- `inc/Smartling/ContentTypes/ExternalContentElementor4.php` — Elementor 4.x handler
- `inc/Smartling/ContentTypes/Elementor/ElementAbstract.php` — Base element class (v3)
- `inc/Smartling/ContentTypes/Elementor/ElementAbstract4.php` — Base element class (v4, `$$type`-aware)
- `inc/Smartling/ContentTypes/Elementor/ElementFactory3.php` — Factory that loads `Elements/`
- `inc/Smartling/ContentTypes/Elementor/ElementFactory4.php` — Factory that loads `Elements/` + `Elements4/`
- `inc/Smartling/ContentTypes/Elementor/Elements/Unknown.php` — Default handler (recursive child processing)
- `inc/Smartling/ContentTypes/Elementor/Elements/` — Elementor 3 widget handlers
- `inc/Smartling/ContentTypes/Elementor/Elements4/` — Elementor 4 widget handlers
- `inc/Smartling/Models/RelatedContentInfo.php` — Related content container
- `inc/Smartling/Models/Content.php` — Content reference model
- `tests/Smartling/ContentTypes/Elementor/` — Test examples
- `tests/Smartling/ContentTypes/ExternalContentElementor4Test.php` — Elementor 4 handler tests
