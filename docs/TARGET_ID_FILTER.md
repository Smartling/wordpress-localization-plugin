# `smartling_target_id` filter

Returns the id of the translated counterpart of a piece of content, using the source/target mapping the connector stores in submissions. Use it in custom code (theme, snippets) instead of hardcoding ids that differ between blogs.

```php
// attachment 13574 in the source blog => attachment in the current blog
$id = apply_filters('smartling_target_id', 13574, 'attachment');

// keep the original id when there is no translation
$id = apply_filters('smartling_target_id', 13574, 'attachment', null, true);
```

| # | Argument | Default | Notes |
|---|----------|---------|-------|
| 1 | `$sourceId` | | id in the source blog; returned as is when the connector is inactive |
| 2 | `$contentType` | `''` | submission content type (`attachment`, `post`, `page`, `category`, ...); empty ignores the type |
| 3 | `$targetBlogId` | current blog | blog to get the id for |
| 4 | `$fallbackToSource` | `false` | return `$sourceId` instead of `null` when no translation is found |
| 5 | `$sourceBlogId` | any | blog the content was translated from |

Returns `int|null`. `null` is returned when no submission matches, when its target id is not set yet, or when more than one submission matches (pass `$sourceBlogId` or `$contentType` to disambiguate).
