# Elementor Content Processing Guide

This document captures key patterns and information for developing Elementor widget support in the Smartling Connector plugin.

## Architecture Overview

### Element Class Hierarchy
- **ElementAbstract** - Base class for all Elementor elements
- **Unknown** - Default handler for unrecognized widgets, provides common functionality
- **Specific Elements** (e.g., LoopCarousel, ImageGallery, Template) - Extend Unknown to add widget-specific behavior

### Key Methods

#### `getRelated(): RelatedContentInfo`
Identifies related content (posts, attachments, taxonomy terms) that need translation. Always call `parent::getRelated()` first to inherit base functionality.

#### `getTranslatableStrings(): array`
Returns translatable text strings from the widget settings.

#### `setRelations()`
Replaces source content IDs with target (translated) content IDs during download. This method automatically handles content type detection:
- For `CONTENT_TYPE_POST`: Calls `get_post_type()` to determine the actual post type
- For `CONTENT_TYPE_TAXONOMY`: Calls `get_term()` to determine the actual taxonomy name (e.g., 'product_tag', 'category')
- For specific types (e.g., 'attachment', 'product_tag'): Uses the type as-is

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

### Pattern 3: Processing Nested Arrays

When content is nested deeper in the settings structure:

```php
foreach ($this->settings['items'] ?? [] as $index => $item) {
    $key = "items/$index/image/id";
    $id = $this->getIntSettingByKey($key, $this->settings);
    // ... process
}
```

**Example:** `Elements/IconList.php`

## Content Types

### Available Content Type Constants

From `ContentTypeHelper`:
- `ContentTypeHelper::CONTENT_TYPE_POST` - For posts, pages, custom post types
- `ContentTypeHelper::CONTENT_TYPE_TAXONOMY` - For taxonomy terms (generic)
- `ContentTypeHelper::POST_TYPE_ATTACHMENT` - For media attachments

### Taxonomy Terms

When processing taxonomy terms (categories, tags, custom taxonomies):

**Recommended: Use generic taxonomy constant**
```php
new Content($termId, ContentTypeHelper::CONTENT_TYPE_TAXONOMY)
```

The `ElementAbstract::setRelations()` method automatically detects the actual taxonomy name (e.g., 'product_tag', 'category') by calling `get_term()` on the term ID. This is the preferred approach as it's more flexible and doesn't require knowing the taxonomy type in advance.

**Alternative: Use specific taxonomy name (if known)**
```php
new Content($termId, 'product_tag')  // When taxonomy type is known
```

This approach can be used when you know the exact taxonomy upfront, but the generic `CONTENT_TYPE_TAXONOMY` is generally preferred.

## Widget Settings Structure

### LoopCarousel Widget Example

The LoopCarousel widget filters content using these key settings:

```json
{
  "template_id": 1690,
  "post_query_post_type": "product",
  "post_query_include": ["terms"],
  "post_query_include_term_ids": ["14", "15", "16"]
}
```

- `template_id` - The Elementor template used for each item (post reference)
- `post_query_include_term_ids` - Array of term IDs used to filter displayed posts (taxonomy references)

### Common Settings Patterns

- IDs are often stored as strings even when numeric: `"14"` not `14`
- Arrays use numeric indices: `post_query_include_term_ids/0`, `post_query_include_term_ids/1`
- Nested paths use forward slashes: `settings/wp_gallery/1/id`

## Testing Patterns

### Test 1: Verify Related Content Discovery

Tests that related content is properly identified:

```php
public function testRelatedContent(): void
{
    $relatedList = (new MyWidget([
        'settings' => [
            'some_ids' => ['14', '15', '16']
        ]
    ]))->getRelated()->getRelatedContentList();

    $this->assertArrayHasKey('expected_type', $relatedList);
    $this->assertEquals(['14', '15', '16'], $relatedList['expected_type']);
}
```

### Test 2: Verify Content Translation

Tests that IDs are replaced with translated equivalents:

```php
public function testTranslation(): void
{
    $sourceId = 14;
    $targetId = 28;

    $externalContentElementor = $this->createMock(ExternalContentElementor::class);
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

## Development Workflow

### Adding Support for a New Widget

1. **Create Element Class** in `inc/Smartling/ContentTypes/Elementor/Elements/`
   - Extend `Unknown`
   - Override `getType()` to return widget type string
   - Override `getRelated()` if widget has related content

2. **Identify Widget Structure**
   - Export Elementor page JSON to examine widget settings
   - Look for ID fields: `*_id`, `*_ids`, nested arrays
   - Determine content types (posts, attachments, terms)

3. **Implement `getRelated()`**
   - Call `parent::getRelated()` first
   - Loop through settings to find content references
   - Use `addContent()` with proper content type and path

4. **Create Tests** in `tests/Smartling/ContentTypes/Elementor/`
   - Test related content discovery
   - Test ID translation
   - Include edge cases (empty arrays, missing settings)

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

### Case Study: ImageGallery

**Problem:** Image galleries contain multiple attachments that need translation.

**Implementation:**
```php
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
```

## Tips and Best Practices

1. **Always use null-coalescing operator** (`??`) when accessing settings arrays
2. **Build proper paths** - They must match the exact structure in settings JSON
3. **Use `getIntSettingByKey()`** - Handles nested path resolution and type casting
4. **Call parent methods** - Don't skip `parent::getRelated()` unless you have a specific reason
5. **Check for numeric before casting** - Settings often store numbers as strings
6. **Write tests** - Test both discovery and translation of related content
7. **Use constants** - Prefer `ContentTypeHelper::CONTENT_TYPE_*` over magic strings
8. **Document widget structure** - Add comments showing the expected settings structure

## Debugging Tips

### Common Issues
- **Path mismatch** - Ensure path in `addContent()` matches exact JSON structure
- **Type mismatch** - Check if IDs are stored as strings vs integers
- **Missing null checks** - Always use `??` for optional settings
- **Wrong content type** - Verify you're using the correct type constant

## Related Files

- `inc/Smartling/ContentTypes/Elementor/ElementAbstract.php` - Base element class
- `inc/Smartling/ContentTypes/Elementor/Elements/Unknown.php` - Default handler
- `inc/Smartling/ContentTypes/ExternalContentElementor.php` - Main Elementor integration
- `inc/Smartling/Models/RelatedContentInfo.php` - Related content container
- `inc/Smartling/Models/Content.php` - Content reference model
- `tests/Smartling/ContentTypes/Elementor/` - Test examples
