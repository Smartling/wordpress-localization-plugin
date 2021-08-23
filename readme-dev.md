# Notes for developers
## Building frontend assets
```shell
cd build && npm run build
```

## Testing
There is a Dockerfile that can be used to create an image to run unit and integration tests.
### Build
```shell
docker build --rm --tag="wordpress-localization-plugin" -f "Buildplan/Dockerfile" --build-arg ACFPRO_KEY="" --build-arg WP_VERSION="latest" --build-arg ACF_PRO_VERSION="latest" --build-arg GITHUB_OAUTH_TOKEN="" .
```

`ACFPRO_KEY` is required to register ACF Pro plugin

`WP_VERSION` is a specific version, e.g "4.5.2" or "nightly", as is `ACF_PRO_VERSION`

`GITHUB_OAUTH_TOKEN` is not strictly required, but without it you're extremely likely to run into issues when composer installs.

### Run

```shell
docker run --rm -it -w /plugin-dir -v /path/to/wordpress-localization-plugin:/plugin-dir -e MYSQL_HOST="localhost" -e CRE_PROJECT_ID= -e CRE_USER_IDENTIFIER= -e CRE_TOKEN_SECRET= wordpress-localization-plugin:latest
```

with `CRE_PROJECT_ID`, `CRE_USER_IDENTIFIER` and `CRE_TOKEN_SECRET` taken from Smartling dashboard.

Starting about 2020-06, there is a WordPress database error while executing tests, regarding a table `wp.wp_2_yoast_indexable` that doesn't exist, but [yoast devs say it doesn't have any negative impact](https://wordpress.org/support/topic/wordpress-database-error-table-12/).

## Releasing
```shell
cd build && npm run build && cd ..
svn commit -m 'Update to v x.y.z' --username smartling
svn copy https://plugins.svn.wordpress.org/smartling-connector/trunk https://plugins.svn.wordpress.org/smartling-connector/tags/x.y.z -m 'Tagging new version x.y.z'
```

# Dev changelog
## Function signature changes in 2.5.0
### inc/Smartling/Bootstrap/Bootstrap.php
`- public function activate()`

`+ public function activate(): void`

`- public function deactivate()`

`+ public function deactivate(): void`

`- public function registerHooks()`

`- public function load()`

`+ public function load(): void`

`- public function detectInstalledMultilangPlugins()`

`- public function updateGlobalExpertSettings()`

`+ public function updateGlobalExpertSettings(): void`

`- public function initializeContentTypes()`

`- public function run()`

### inc/Smartling/ContentTypes/AutoDiscover/PostTypes.php
`- public function __construct(ContainerBuilder $di)`

`+ public function __construct(array $ignoredTypes)`

`- public function getDi()`

`- public function setDi($di)`

`- public function getLogger()`

`- public function getIgnoredTypes()`

`- public function setIgnoredTypes($ignoredTypes)`

`- public function setLogger($logger)`

`- public function hookHandler($postType)`

`+ public function hookHandler($postType): void`

### inc/Smartling/ContentTypes/AutoDiscover/Taxonomies.php
`- public function __construct(ContainerBuilder $di)`

`+ public function __construct(array $ignoredTypes)`

`- public function getDi()`

`- public function setDi($di)`

`- public function getLogger()`

`- public function getIgnoredTypes()`

`- public function setIgnoredTypes($ignoredTypes)`

`- public function setLogger($logger)`

`- public function hookHandler($postType)`

`+ public function hookHandler($postType): void`

### inc/Smartling/DbAl/LocalizationPluginAbstract.php
Removed, any classes extending it should implement LocalizationPluginProxyInterface instead

### inc/Smartling/DbAl/LocalizationPluginProxyInterface.php
`- public function getLogger();`

`- public function getLocales();`

`- public function getBlogLocaleById($blogId);`

`+ public function getBlogLocaleById(int $blogId): string;`

`- public function getLinkedBlogIdsByBlogId($blogId);`

`- public function linkObjects(SubmissionEntity $submission);`

`+ public function linkObjects(SubmissionEntity $submission): bool;`

`- public function getLinkedObjects($sourceBlogId, $sourceContentId, $contentType);`

`- public function unlinkObjects(SubmissionEntity $submission);`

`+ public function unlinkObjects(SubmissionEntity $submission): bool;`

`- public function getBlogLanguageById($blogId);`

`- public function getBlogNameByLocale($locale);`

`+ public function getBlogNameByLocale(string $locale): string;`

### inc/Smartling/Helpers/EntityHelper.php
`- public function getOriginalContentId($id, $type = 'post')`

`- public function getTarget($id, $targetBlog, $type = 'post')`

## Function signature changes in 2.4.3
### inc/Smartling/Helpers/FileUriHelper.php
`- public static function generateFileUri(SubmissionEntity $submission)`

`+ public static function generateFileUri(SubmissionEntity $submission): string`
## Function signature changes from 1.5.7 to 2.4.0
### inc/Smartling/ApiWrapper.php
`- public function createJob(ConfigurationProfileEntity $profile, array $params)`

`+ public function createJob(ConfigurationProfileEntity $profile, array $params): array`

`- public function createBatch(ConfigurationProfileEntity $profile, $jobId, $authorize = false)`

`+ public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array`

`- public function retrieveBatchForBucketJob(ConfigurationProfileEntity $profile, $authorize)`

`+ public function retrieveJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, bool $authorize): JobEntityWithBatchUid`

### inc/Smartling/ApiWrapperInterface.php
`- public function createJob(ConfigurationProfileEntity $profile, array $params);`

`+ public function createJob(ConfigurationProfileEntity $profile, array $params): array;`

`- public function createBatch(ConfigurationProfileEntity $profile, $jobId, $authorize = false);`

`+ public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array;`

`- public function retrieveBatchForBucketJob(ConfigurationProfileEntity $profile, $authorize);`

`+ public function retrieveJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, bool $authorize): JobEntityWithBatchUid;`

### inc/Smartling/Base/SmartlingCore.php
`- public function __construct()`

`+ public function __construct(PostContentHelper $postContentHelper, XmlHelper $xmlHelper)`

### inc/Smartling/Base/SmartlingCoreAbstract.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

### inc/Smartling/Base/SmartlingCoreDownloadTrait.php
`- public function downloadTranslationBySubmission(SubmissionEntity $entity)`

`+ public function downloadTranslationBySubmission(SubmissionEntity $entity): void`

### inc/Smartling/Base/SmartlingCoreExportApi.php
`- public function sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $sourceId, $batchUid, $clone = false)`

`+ public function sendAttachmentForTranslation(int $sourceBlogId, int $targetBlogId, int $sourceId, JobEntityWithBatchUid $jobInfo, bool $clone = false): SubmissionEntity`

### inc/Smartling/Base/SmartlingCoreUploadTrait.php
`- public function sendForTranslationBySubmissionId($id)`

`- public function getXMLFiltered(SubmissionEntity $submission)`

`+ public function getXMLFiltered(SubmissionEntity $submission): string`

`- public function applyXML(SubmissionEntity $submission, $xml, XmlHelper $xmlHelper)`

`+ public function applyXML(SubmissionEntity $submission, string $xml, XmlHelper $xmlHelper, PostContentHelper $postContentHelper): array`

`- public function bulkSubmit(SubmissionEntity $submission)`

`+ public function bulkSubmit(SubmissionEntity $submission): void`

`- public function sendForTranslationBySubmission(SubmissionEntity $submission)`

`+ public function sendForTranslationBySubmission(SubmissionEntity $submission): void`

`- public function createForTranslation($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null, $clone = false, $batchUid = '')`

`+ public function createForTranslation(string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, JobEntityWithBatchUid $jobInfo, bool $clone): SubmissionEntity`

### inc/Smartling/ContentTypes/ContentTypeNavigationMenu.php
`- public function gatherRelatedContent(ProcessRelatedContentParams $params)`

`+ public function gatherRelatedContent(ProcessRelatedContentParams $params): void`

### inc/Smartling/DbAl/DB.php
`+ public function prepareSql(array $tableDefinition): string`

`- public function escape($string)`

`+ public function escape(string $string): string`

`- public function completeTableName($tableName)`

`+ public function completeTableName(string $tableName): string`

`- public function completeMultisiteTableName($tableName)`

`+ public function completeMultisiteTableName(string $tableName): string`

`- public function query($query)`

`+ public function query(string $query)`

`- public function fetch($query, $output = OBJECT)`

`+ public function queryPrepared(string $query, ...$args)`

`+ public function fetch(string $query, string $output = OBJECT)`

`- public function getLastInsertedId()`

`+ public function fetchPrepared(string $query, ...$args): array`

`+ public function getLastInsertedId(): int`

`- public function getLastErrorMessage()`

`+ public function getLastErrorMessage(): string`

### inc/Smartling/DbAl/SmartlingToCMSDatabaseAccessWrapperInterface.php
`+ public function query(string $query);`

`+ public function queryPrepared(string $query, ...$args);`

`- public function fetch($query, $output = OBJECT);`

`+ public function fetch(string $query, string $output = OBJECT);`

`+ public function fetchPrepared(string $query, ...$args): array;`

`+ public function escape(string $string): string;`

`- public function completeMultisiteTableName($tableName);`

`+ public function completeTableName(string $tableName): string;`

`+ public function completeMultisiteTableName(string $tableName): string;`

`+ public function getLastInsertedId(): int;`

`+ public function getLastErrorMessage(): string;`

### inc/Smartling/Extensions/Acf/AcfDynamicSupport.php
`- public function getDefinitions()`

`+ public function getDefinitions(): array`

### inc/Smartling/Helpers/AbsoluteLinkedAttachmentCoreHelper.php
`- public function getAttachmentIdByURL($url, $blogId)`

`+ public function getAttachmentIdByURL(string $url, int $blogId): ?int`

`- public function getImagesIdsFromString($string, $blogId)`

`+ public function getImagesIdsFromString(string $string, int $blogId): array`

### inc/Smartling/Helpers/ContentSerializationHelper.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

`- public function getContentHelper()`

`- public function setContentHelper($contentHelper)`

`- public function getFieldsFilter()`

`- public function setFieldsFilter($fieldsFilter)`

`- public function __construct(ContentHelper $contentHelper, FieldsFilterHelper $fieldsFilter)`

`+ public function __construct(ContentHelper $contentHelper)`

`- public function calculateHash(SubmissionEntity $submission)`

`+ public function calculateHash(SubmissionEntity $submission): string`

### inc/Smartling/Helpers/CustomScheduleIntervalHelper.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Helpers/DetectChangesHelper.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

### inc/Smartling/Helpers/EntityHelper.php
`- public function getSiteHelper()`

`+ public function getSiteHelper(): SiteHelper`

### inc/Smartling/Helpers/FieldsFilterHelper.php
`- public function flatternArray(array $array, $base = '', $divider = self::ARRAY_DIVIDER)`

`+ public function flattenArray(array $array, string $base = '', string $divider = self::ARRAY_DIVIDER): array`

`- public function processStringsBeforeEncoding(SubmissionEntity $submission, array $data, $strategy = self::FILTER_STRATEGY_UPLOAD)`

`+ public function processStringsBeforeEncoding(`

`- public function removeEmptyFields(array $array)`

`+ public function removeEmptyFields(array $array): array`

### inc/Smartling/Helpers/GutenbergBlockHelper.php
`- public function registerFilters(array $definitions)`

`+ public function __construct(MediaAttachmentRulesManager $rulesManager, ReplacerFactory $replacerFactory)`

`+ public function registerFilters(array $definitions): array`

`- public function register()`

`+ public function register(): void`

`- public function processAttributes($blockName, array $flatAttributes)`

`+ public function processAttributes(?string $blockName, array $flatAttributes): array`

`+ public function hasBlocks(string $string): bool`

`- public function processString(TranslationStringFilterParameters $params)`

`+ public function processString(TranslationStringFilterParameters $params): TranslationStringFilterParameters`

`+ public function addPostContentBlocks(array $entityFields): array`

`- public function sortChildNodesContent(\DOMNode $node)`

`+ public function getPostContentBlocks(string $string): array`

`+ public function sortChildNodesContent(\DOMNode $node, SubmissionEntity $submission): array`

`- public function renderTranslatedBlockNode(\DOMElement $node)`

`+ public function renderTranslatedBlockNode(\DOMElement $node, SubmissionEntity $submission): string`

`- public function renderGutenbergBlock($name, array $attrs = [], array $chunks = [])`

`+ public function renderGutenbergBlock(string $name, array $attrs = [], array $chunks = []): string`

`- public function processTranslation(TranslationStringFilterParameters $params)`

`+ public function processTranslation(TranslationStringFilterParameters $params): TranslationStringFilterParameters`

`- public function loadExternalDependencies()`

`+ public function loadExternalDependencies(): void`

### inc/Smartling/Helpers/GutenbergReplacementRule.php
`- public function __construct($type, $property)`

`+ public function __construct(string $blockType, string $propertyPath, string $replacerId)`

`- public function getProperty()`

`+ public function getBlockType(): string`

`- public function getType()`

`+ public function getPropertyPath(): string`

`+ public function getReplacerId(): string`

### inc/Smartling/Helpers/MetaFieldProcessor/MetaFieldProcessorManager.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Helpers/QueryBuilder/Condition/Condition.php
`- public function __toString()`

`+ public function __toString(): string`

### inc/Smartling/Helpers/QueryBuilder/Condition/ConditionBlock.php
`- public function __construct($conditionOperator)`

`+ public function __construct(string $conditionOperator)`

`- public function addCondition(Condition $condition)`

`+ public function addCondition(Condition $condition): void`

`- public function addConditionBlock(ConditionBlock $block)`

`+ public function isEmpty(): bool`

`+ public function addConditionBlock(ConditionBlock $block): void`

`- public function __toString()`

`+ public function __toString(): string`

### inc/Smartling/Helpers/RelativeLinkedAttachmentCoreHelper.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

`- public function getCore()`

`- public function getParams()`

`+ public function getParams(): AfterDeserializeContentEventParameters`

`- public function __construct(SmartlingCore $core, AcfDynamicSupport $acfDynamicSupport, MediaAttachmentRulesManager $mediaAttachmentRulesManager)`

`+ public function __construct(SmartlingCore $core, AcfDynamicSupport $acfDynamicSupport)`

`- public function register()`

`+ public function register(): void`

`- public function processor(AfterDeserializeContentEventParameters $params)`

`+ public function processor(AfterDeserializeContentEventParameters $params): void`

### inc/Smartling/Helpers/ReplacementInfo.php
`- public function __construct($replacement, $sourceId, $targetId)`

`+ public function __construct(string $resultString, array $replacementPairs)`

`- public function getReplacement()`

`- public function getSourceId()`

`+ public function getResult(): string`

`- public function getTargetId()`

`+ public function getReplacementPairs(): array`

### inc/Smartling/Helpers/ReplacementPair.php
`- public function __construct($from, $to)`

`+ public function __construct(string $from, string $to)`

`- public function getFrom()`

`+ public function getFrom(): string`

`- public function getTo()`

`+ public function getTo(): string`

### inc/Smartling/Helpers/ShortcodeHelper.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Helpers/SiteHelper.php
`- public function getInitialBlogId()`

`+ public function getInitialBlogId(): int`

`- public function listSites()`

`+ public function listSites(): array`

`- public function listBlogs($siteId = 1)`

`+ public function listBlogs(int $siteId = 1): array`

`- public function listBlogIdsFlat()`

`+ public function listBlogIdsFlat(): array`

`- public function getCurrentSiteId()`

`+ public function getCurrentSiteId(): int`

`- public function getCurrentBlogId()`

`+ public function getCurrentBlogId(): int`

`- public function getCurrentUserLogin()`

`+ public function getCurrentUserLogin(): ?string`

`- public function switchBlogId($blogId)`

`+ public function switchBlogId(int $blogId): void`

`- public function restoreBlogId()`

`+ public function restoreBlogId(): void`

`+ public function withBlog(int $blogId, callable $function)`

`- public function getBlogLabelById($localizationPluginProxyInterface, $blogId)`

`+ public function getBlogLabelById(LocalizationPluginProxyInterface $localizationPluginProxyInterface, int $blogId): string`

`- public function getCurrentBlogLocale(LocalizationPluginProxyInterface $localizationPlugin)`

`+ public function getCurrentBlogLocale(LocalizationPluginProxyInterface $localizationPlugin): string`

`- public function getPostTypes()`

`+ public function getPostTypes(): array`

`- public function getTermTypes()`

`+ public function getTermTypes(): array`

`- public function resetBlog($blogId = null)`

`+ public function resetBlog(?int $blogId = null): void`

### inc/Smartling/Helpers/SubmissionCleanupHelper.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Helpers/SubstringProcessorHelperAbstract.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

### inc/Smartling/Helpers/TranslationHelper.php
`- public function __construct() {`

`+ public function __construct(LocalizationPluginProxyInterface $proxy, SiteHelper $siteHelper, SubmissionManager $submissionManager) {`

`- public function getLogger()`

`- public function getSubmissionManager()`

`- public function setSubmissionManager($submissionManager)`

`- public function getMutilangProxy()`

`- public function setMutilangProxy($mutilangProxy)`

`- public function getSiteHelper()`

`- public function setSiteHelper($siteHelper)`

`- public function prepareSubmissionEntity($contentType, $sourceBlog, $sourceEntity, $targetBlog, $targetEntity = null)`

`+ public function prepareSubmissionEntity(string $contentType, int $sourceBlog, int $sourceEntity, int $targetBlog, ?int $targetEntity = null): SubmissionEntity`

`- public function prepareSubmission($contentType, $sourceBlog, $sourceId, $targetBlog, $clone = false)`

`+ public function prepareSubmission(string $contentType, int $sourceBlog, int $sourceId, int $targetBlog, bool $clone = false): SubmissionEntity`

`- public function reloadSubmission(SubmissionEntity $submission)`

`+ public function reloadSubmission(SubmissionEntity $submission): SubmissionEntity`

`- public function isRelatedSubmissionCreationNeeded($contentType, $sourceBlogId, $contentId, $targetBlogId) {`

`+ public function isRelatedSubmissionCreationNeeded(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool {`

`- public function getExistingSubmissionOrCreateNew($contentType, $sourceBlogId, $contentId, $targetBlogId, $batchUid) {`

`+ public function getExistingSubmissionOrCreateNew(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId, JobEntityWithBatchUid $jobInfo): SubmissionEntity {`

`- public function tryPrepareRelatedContent($contentType, $sourceBlog, $sourceId, $targetBlog, $batchUid, $clone = false)`

`+ public function tryPrepareRelatedContent(string $contentType, int $sourceBlog, int $sourceId, int $targetBlog, JobEntityWithBatchUid $jobInfo, bool $clone = false): SubmissionEntity`

### inc/Smartling/Jobs/DownloadTranslationJob.php
`- public function getJobHookName()`

`+ public function getJobHookName(): string`

`- public function run()`

`+ public function run(): void`

### inc/Smartling/Jobs/JobAbstract.php
`- public function getJobRunInterval()`

`+ public function getJobRunInterval(): string`

`- public function install()`

`+ public function install(): void`

`- public function uninstall()`

`+ public function uninstall(): void`

`- public function register()`

`+ public function register(): void`

### inc/Smartling/Jobs/JobInterface.php
`- public function install();`

`+ public function install(): void;`

`- public function uninstall();`

`+ public function uninstall(): void;`

`- public function getJobRunInterval();`

`+ public function getJobRunInterval(): string;`

`- public function getJobHookName();`

`+ public function getJobHookName(): string;`

`- public function run();`

`+ public function run(): void;`

### inc/Smartling/Jobs/LastModifiedCheckJob.php
`- public function getJobHookName()`

`+ public function getJobHookName(): string`

`- public function run()`

`+ public function run(): void`

### inc/Smartling/Jobs/SubmissionCollectorJob.php
`- public function getJobHookName()`

`+ public function getJobHookName(): string`

`- public function run()`

`+ public function run(): void`

### inc/Smartling/Jobs/UploadJob.php
`- public function getApi()`

`- public function setApi($api)`

`- public function getSettingsManager()`

`- public function setSettingsManager($settingsManager)`

`- public function getJobHookName()`

`+ public function getJobHookName(): string`

`- public function run()`

`+ public function run(): void`

### inc/Smartling/Services/BaseAjaxServiceAbstract.php
`- public function register()`

`+ public function register(): void`

`- public function getRequestSource()`

`+ public function getRequestSource(): array`

`- public function getRequestVariable($varName, $defaultValue = false)`

`+ public function getRequestVariable(string $varName, $defaultValue = null)`

`- public function returnResponse(array $data, $responseCode = 200)`

`+ public function returnResponse(array $data, $responseCode = 200): void`

`- public function returnError($key, $message, $responseCode = 400)`

`+ public function returnError($key, $message, $responseCode = 400): void`

`- public function getRequiredParam($paramName)`

`+ public function getRequiredParam(string $paramName): string`

`- public function returnSuccess($data, $responseCode = 200)`

`+ public function returnSuccess($data, $responseCode = 200): void`

### inc/Smartling/Services/BlogRemovalHandler.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Services/ContentRelationsDiscoveryService.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

`- public function getContentHelper()`

`- public function setContentHelper($contentHelper)`

`- public function getFieldFilterHelper()`

`- public function setFieldFilterHelper($fieldFilterHelper)`

`- public function getMetaFieldProcessorManager()`

`- public function setMetaFieldProcessorManager($metaFieldProcessorManager)`

`- public function getAbsoluteLinkedAttachmentCoreHelper()`

`- public function setAbsoluteLinkedAttachmentCoreHelper($absoluteLinkedAttachmentCoreHelper)`

`- public function getShortcodeHelper()`

`- public function setShortcodeHelper($shortcodeHelper)`

`- public function getGutenbergBlockHelper()`

`- public function setGutenbergBlockHelper($gutenbergBlockHelper)`

`- public function getSubmissionManager()`

`- public function setSubmissionManager($submissionManager)`

`- public function getApiWrapper()`

`- public function setApiWrapper($apiWrapper)`

`- public function getSettingsManager()`

`- public function setSettingsManager($settingsManager)`

`- public function register()`

`+ public function register(): void`

`- public function bulkUploadHandler($batchUid, array $contentIds, $contentType, $currentBlogId, array $targetBlogIds) {`

`+ public function bulkUploadHandler(JobEntityWithBatchUid $jobInfo, array $contentIds, string $contentType, int $currentBlogId, array $targetBlogIds): void`

`- public function createSubmissionsHandler($data = '')`

`+ public function createSubmissionsHandler($data = ''): void`

`- public function getRequestSource()`

`+ public function getRequestSource(): array`

`- public function getContentType()`

`+ public function getContentType(): string`

`- public function getId()`

`+ public function getId(): int`

`- public function getTargetBlogIds()`

`+ public function getTargetBlogIds(): array`

`- public function shortcodeHandler(array $attributes, $content, $shortcodeName)`

`+ public function shortcodeHandler(array $attributes, string $content, string $shortcodeName): void`

`- public function actionHandler()`

`+ public function actionHandler(): void`

### inc/Smartling/Services/InvalidCharacterCleaner.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Services/SmartlingFilterUiService.php
`- public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager)`

`+ public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager, ReplacerFactory $replacerFactory)`

`- public function register()`

`+ public function register(): void`

### inc/Smartling/Settings/ConfigurationProfileEntity.php
`- public function getId()`

`+ public function getId(): int`

`- public function setId($id)`

`+ public function setId(int $id): void`

`- public function getProfileName()`

`+ public function getProfileName(): string`

`- public function setProfileName($profileName)`

`+ public function setProfileName(string $profileName): void`

`- public function getProjectId()`

`+ public function getProjectId(): string`

`- public function setProjectId($projectId)`

`+ public function setProjectId(string $projectId): void`

`- public function getUserIdentifier()`

`+ public function getUserIdentifier(): string`

`- public function setUserIdentifier($user_identifier)`

`+ public function setUserIdentifier(string $user_identifier): void`

`- public function getSecretKey()`

`+ public function getSecretKey(): string`

`- public function setSecretKey($secret_key)`

`+ public function setSecretKey(string $secret_key): void`

`- public function getIsActive()`

`+ public function getIsActive(): int`

`- public function setIsActive($isActive)`

`+ public function setIsActive(int $isActive): void`

`- public function getOriginalBlogId()`

`+ public function getOriginalBlogId(): Locale`

`- public function setOriginalBlogId($mainLocale)`

`+ public function setLocale(Locale $mainLocale): void`

`- public function getAutoAuthorize()`

`+ public function setOriginalBlogId(int $blogId): void`

`+ public function getAutoAuthorize(): bool`

`- public function setAutoAuthorize($autoAuthorize)`

`+ public function setAutoAuthorize(bool $autoAuthorize): void`

`- public function getRetrievalType()`

`+ public function getRetrievalType(): string`

`- public function setRetrievalType($retrievalType)`

`+ public function setRetrievalType(string $retrievalType): void`

`- public function getTargetLocales()`

`+ public function getTargetLocales(): array`

`- public function setTargetLocales($targetLocales)`

`+ public function setTargetLocales(array $targetLocales): void`

`- public function getFilterFieldNameRegExp()`

`+ public function getFilterFieldNameRegExp(): bool`

`- public function setFilterFieldNameRegexp($value)`

`+ public function setFilterFieldNameRegexp(?bool $value): void`

`- public function getFilterSkip()`

`+ public function getFilterSkip(): string`

`- public function getFilterSkipArray()`

`+ public function getFilterSkipArray(): array`

`- public function setFilterSkip($value)`

`+ public function setFilterSkip(string $value): void`

`- public function getFilterCopyByFieldName()`

`+ public function getFilterCopyByFieldName(): string`

`- public function setFilterCopyByFieldName($value)`

`+ public function setFilterCopyByFieldName(string $value): void`

`- public function getFilterCopyByFieldValueRegex()`

`+ public function getFilterCopyByFieldValueRegex(): string`

`- public function setFilterCopyByFieldValueRegex($value)`

`+ public function setFilterCopyByFieldValueRegex(string $value): void`

`- public function getFilterFlagSeo()`

`+ public function getFilterFlagSeo(): string`

`- public function setFilterFlagSeo($value)`

`+ public function setFilterFlagSeo(string $value): void`

`- public function getUploadOnUpdate()`

`+ public function getUploadOnUpdate(): int`

`- public function setUploadOnUpdate($uploadOnUpdate)`

`+ public function setUploadOnUpdate(int $uploadOnUpdate): void`

`- public function setDownloadOnChange($downloadOnChange)`

`+ public function setDownloadOnChange(int $downloadOnChange): void`

`- public function getDownloadOnChange()`

`+ public function getDownloadOnChange(): int`

`- public function setCleanMetadataOnDownload($cleanMetadataOnDownload)`

`+ public function setCleanMetadataOnDownload(int $cleanMetadataOnDownload): void`

`- public function getCleanMetadataOnDownload()`

`+ public function getCleanMetadataOnDownload(): int`

`- public function getPublishCompleted()`

`+ public function getTranslationPublishingMode(): int`

`- public function setPublishCompleted($publishCompleted)`

`+ public function setChangeAssetStatusOnCompletedTranslation(int $status): void`

`+ public function setPublishCompleted(int $publishCompleted): void`

`- public function setCloneAttachment($cloneAttachment)`

`+ public function setCloneAttachment(?int $cloneAttachment): void`

`- public function getCloneAttachment()`

`+ public function getCloneAttachment(): int`

`- public function setAlwaysSyncImagesOnUpload($alwaysSyncImagesOnUpload)`

`+ public function setAlwaysSyncImagesOnUpload(int $alwaysSyncImagesOnUpload): void`

`- public function getAlwaysSyncImagesOnUpload()`

`+ public function getAlwaysSyncImagesOnUpload(): int`

`- public function getEnableNotifications()`

`+ public function getEnableNotifications(): int`

`- public function setEnableNotifications($enableNotifications)`

`+ public function setEnableNotifications(?int $enableNotifications): void`

`- public function toArray($addVirtualColumns = true)`

`+ public function toArray($addVirtualColumns = true): array`

`- public function toArraySafe()`

`+ public function toArraySafe(): array`

### inc/Smartling/Settings/Locale.php
`- public function getBlogId()`

`+ public function getBlogId(): int`

`- public function setBlogId($blogId)`

`+ public function setBlogId(int $blogId): void`

`- public function getLabel()`

`+ public function getLabel(): string`

`- public function setLabel($label)`

`+ public function setLabel(string $label): void`

### inc/Smartling/Settings/SettingsManager.php
`- public function getEntities($sortOptions = [], $pageOptions = null, & $totalCount = 0, $onlyActive = false)`

`+ public function getEntities(int &$totalCount = 0, bool $onlyActive = false): array`

`- public function getActiveProfiles()`

`+ public function getActiveProfiles(): array`

`- public function getSmartlingLocaleBySubmission(SubmissionEntity $submission)`

`+ public function getSmartlingLocaleBySubmission(SubmissionEntity $submission): string`

`- public function getSingleSettingsProfile($mainBlogId)`

`+ public function getSingleSettingsProfile(int $mainBlogId): ConfigurationProfileEntity`

`- public function getProfileTargetBlogIdsByMainBlogId($mainBlogId) {`

`+ public function getProfileTargetBlogIdsByMainBlogId(int $mainBlogId): array`

`- public function getSmartlingLocaleIdBySettingsProfile(ConfigurationProfileEntity $profile, $targetBlog)`

`+ public function getSmartlingLocaleIdBySettingsProfile(ConfigurationProfileEntity $profile, int $targetBlog): string`

`- public function getActiveProfileByProjectId($projectId)`

`+ public function getActiveProfileByProjectId(string $projectId): ConfigurationProfileEntity`

`- public function getEntityById($id)`

`+ public function getEntityById(int $id): array`

`- public function buildCountQuery()`

`+ public function buildCountQuery(): string`

`- public function fetchData($query)`

`+ public function fetchData($query): array`

`- public function findEntityByMainLocale($sourceBlogId)`

`+ public function findEntityByMainLocale(int $sourceBlogId): array`

`+ public function storeEntity(ConfigurationProfileEntity $entity): ConfigurationProfileEntity`

`- public function storeEntity(ConfigurationProfileEntity $entity)`

`- public function createProfile(array $fields)`

`+ public function createProfile(array $fields): ConfigurationProfileEntity`

### inc/Smartling/Submissions/SubmissionEntity.php
`- public function getLastModified()`

`+ public function getLastModified(): \DateTime`

`- public function setLastModified($dateTime)`

`+ public function setLastModified($dateTime): void`

`- public function getOutdated()`

`+ public function getOutdated(): int`

`- public function setOutdated($outdated)`

`+ public function setOutdated(?int $outdated): void`

`- public function getIsCloned()`

`+ public function getIsCloned(): int`

`- public function setIsCloned($isCloned)`

`+ public function setIsCloned(?int $isCloned): void`

`- public function getWordCount()`

`+ public function getWordCount(): int`

`- public function setWordCount($word_count)`

`+ public function setWordCount(?int $word_count): void`

`- public function getIsLocked()`

`+ public function getIsLocked(): int`

`- public function setIsLocked($is_locked)`

`+ public function setIsLocked(?int $is_locked): void`

`- public function getStatus()`

`+ public function getStatus(): string`

`- public function setStatus($status)`

`+ public function setStatus(string $status): SubmissionEntity`

`- public function hasLocks()`

`+ public function hasLocks(): bool`

`- public function getStatusFlags()`

`+ public function getStatusFlags(): array`

`- public function getStatusColor()`

`+ public function getStatusColor(): string`

`- public function getId()`

`+ public function getId(): ?int`

`- public function setId($id)`

`+ public function setId(?int $id): SubmissionEntity`

`- public function getSourceTitle($withReplacement = true)`

`+ public function getSourceTitle(bool $withReplacement = true): string`

`- public function setSourceTitle($source_title)`

`+ public function setSourceTitle(string $source_title): SubmissionEntity`

`- public function getSourceBlogId()`

`+ public function getSourceBlogId(): int`

`- public function setSourceBlogId($source_blog_id)`

`+ public function setSourceBlogId(int $source_blog_id): SubmissionEntity`

`- public function getSourceContentHash()`

`+ public function getSourceContentHash(): string`

`- public function setSourceContentHash($source_content_hash)`

`+ public function setSourceContentHash(?string $source_content_hash): SubmissionEntity`

`- public function getContentType()`

`+ public function getContentType(): string`

`- public function setContentType($content_type)`

`+ public function setContentType(string $content_type): SubmissionEntity`

`- public function getSourceId()`

`+ public function getSourceId(): int`

`- public function setSourceId($source_id)`

`+ public function setSourceId(int $source_id): SubmissionEntity`

`- public function getFileUri()`

`+ public function getFileUri(): string`

`- public function getStateFieldFileUri() {`

`+ public function getStateFieldFileUri(): string`

`- public function getTargetLocale()`

`+ public function getTargetLocale(): string`

`- public function setTargetLocale($target_locale)`

`+ public function setTargetLocale(?string $target_locale): SubmissionEntity`

`- public function getTargetBlogId()`

`+ public function getTargetBlogId(): int`

`- public function setTargetBlogId($target_blog_id)`

`+ public function setTargetBlogId(int $target_blog_id): SubmissionEntity`

`- public function getTargetId()`

`+ public function getTargetId(): int`

`- public function setTargetId($target_id)`

`+ public function setTargetId(?int $target_id): SubmissionEntity`

`- public function getSubmitter()`

`+ public function getSubmitter(): string`

`- public function setSubmitter($submitter)`

`+ public function setSubmitter(string $submitter): SubmissionEntity`

`- public function getSubmissionDate()`

`+ public function getSubmissionDate(): string`

`- public function setSubmissionDate($submission_date)`

`+ public function setSubmissionDate(string $submission_date): SubmissionEntity`

`- public function getAppliedDate()`

`+ public function getAppliedDate(): ?string`

`- public function setAppliedDate($applied_date)`

`+ public function setAppliedDate(?string $applied_date): void`

`- public function getApprovedStringCount()`

`+ public function getApprovedStringCount(): int`

`- public function setApprovedStringCount($approved_string_count)`

`+ public function setApprovedStringCount(int $approved_string_count): SubmissionEntity`

`- public function getCompletedStringCount()`

`+ public function getCompletedStringCount(): int`

`- public function setCompletedStringCount($completed_string_count)`

`+ public function setCompletedStringCount(int $completed_string_count): SubmissionEntity`

`- public function getExcludedStringCount()`

`+ public function getExcludedStringCount(): int`

`- public function setExcludedStringCount($excludedStringsCount)`

`+ public function setExcludedStringCount(?int $excludedStringsCount): SubmissionEntity`

`- public function getTotalStringCount()`

`+ public function getTotalStringCount(): int`

`- public function setTotalStringCount($totalStringsCount)`

`+ public function setTotalStringCount(?int $totalStringsCount): SubmissionEntity`

`- public function getCompletionPercentage()`

`+ public function getCompletionPercentage(): int`

`- public function getLastError()`

`+ public function getLastError(): string`

`- public function setLastError($message)`

`+ public function setLastError(string $message): void`

`- public function getBatchUid()`

`+ public function getBatchUid(): string`

`- public function setBatchUid($batchUid)`

`+ public function setBatchUid($batchUid): void`

`+ public function getJobInfo(): JobEntity`

`+ public function getJobInfoWithBatchUid(): JobEntityWithBatchUid`

`+ public function setJobInfo(JobEntity $jobInfo): void`

`- public function getLockedFields()`

`+ public function getLockedFields(): array`

`- public function setLockedFields($lockFields)`

`+ public function setLockedFields($lockFields): void`

### inc/Smartling/Submissions/SubmissionManager.php
`- public function getSubmissionStatusLabels()`

`- public function getSubmissionStatuses()`

`+ public function getSubmissionStatusLabels(): array`

`- public function getDefaultSubmissionStatus()`

`+ public function getDefaultSubmissionStatus(): string`

`- public function getHelper()`

`- public function getEntityHelper()`

`- public function __construct($dbal, $pageSize, $entityHelper)`

`+ public function __construct(SmartlingToCMSDatabaseAccessWrapperInterface $dbal, int $pageSize, EntityHelper $entityHelper, JobManager $jobManager, SubmissionsJobsManager $submissionsJobsManager)`

`- public function getEntities(`

`- public function submissionExists($contentType, $sourceBlogId, $contentId, $targetBlogId)`

`+ public function submissionExists(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool`

`- public function submissionExistsNoLastError($contentType, $sourceBlogId, $contentId, $targetBlogId)`

`+ public function submissionExistsNoLastError(string $contentType, int $sourceBlogId, int $contentId, int $targetBlogId): bool`

`- public function search(`

`- public function getTotalInUploadQueue()`

`+ public function getTotalInUploadQueue(): int`

`- public function getTotalInCheckStatusHelperQueue()`

`+ public function getTotalInCheckStatusHelperQueue(): int`

`- public function searchByBatchUid($batchUid)`

`+ public function searchByBatchUid(string $batchUid): array`

`- public function getEntityById($id)`

`+ public function getEntityById(int $id): ?array`

`- public function buildSelectQuery(array $where)`

`+ public function buildSelectQuery(array $where): string`

`- public function buildCountQuery($contentType, $status, $outdatedFlag, ConditionBlock $baseCondition = null)`

`+ public function buildCountQuery(?string $contentType, ?string $status, ?int $outdatedFlag, ConditionBlock $baseCondition = null): string`

`- public function filterBrokenSubmissions(array $submissions)`

`- public function validateSubmission(SubmissionEntity $submission, $updateState = true)`

`- public function find(array $params = [], $limit = 0)`

`+ public function find(array $params = [], int $limit = 0): array`

`- public function findSubmissionsForUploadJob()`

`+ public function findSubmissionsForUploadJob(): array`

`- public function findBatchUidNotEmpty(array $params = [], $limit = 0)`

`- public function findByIds(array $ids)`

`+ public function findByIds(array $ids): array`

`- public function getColumnsLabels()`

`+ public function getColumnsLabels(): array`

`- public function getSortableFields()`

`+ public function getSortableFields(): array`

`- public function storeEntity(SubmissionEntity $entity)`

`+ public function storeEntity(SubmissionEntity $entity): SubmissionEntity`

`- public function createSubmission(array $fields)`

`+ public function createSubmission(array $fields): SubmissionEntity`

`- public function findSubmission($contentType, $sourceBlogId, $sourceId, $targetBlogId)`

`- public function storeSubmissions(array $submissions)`

`+ public function storeSubmissions(array $submissions): array`

`- public function delete(SubmissionEntity $submission)`

`+ public function delete(SubmissionEntity $submission): void`

`- public function setErrorMessage(SubmissionEntity $submission, $message)`

`+ public function setErrorMessage(SubmissionEntity $submission, string $message): SubmissionEntity`

### inc/Smartling/Tuner/FilterManager.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/Tuner/MediaAttachmentRulesManager.php
`- public function getPreconfiguredRules() {`

`+ public function getPreconfiguredRules(): array {`

`+ public function getGutenbergReplacementRules(?string $blockType = null, ?string $attribute = null): array`

`- public function getGutenbergReplacementRules()`

`+ public function listItems(): array`

### inc/Smartling/Tuner/ShortcodeManager.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/AdminPage.php
`- public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager)`

`+ public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager, ReplacerFactory $replacerFactory)`

`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/BulkSubmitController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/CheckStatusController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/ConfigurationProfileFormController.php
`- public function wp_enqueue()`

`+ public function wp_enqueue(): void`

`- public function register()`

`+ public function register(): void`

`- public function initTestConnectionEndpoint()`

`+ public function initTestConnectionEndpoint(): void`

`- public function menu()`

`+ public function menu(): void`

`- public function edit()`

`+ public function edit(): void`

`- public function save()`

`+ public function save(): void`

### inc/Smartling/WP/Controller/ConfigurationProfilesController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/ConfigurationProfilesWidget.php
### inc/Smartling/WP/Controller/ContentEditJobController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/FilterForm.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/LiveNotificationController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/MediaRuleForm.php
`- public function register()`

`+ public function __construct(MediaAttachmentRulesManager $mediaAttachmentRulesManager, ReplacerFactory $replacerFactory)`

`+ public function register(): void`

`- public function menu()`

`+ public function menu(): void`

`- public function pageHandler()`

`+ public function pageHandler(): void`

`- public function save()`

`+ public function save(): void`

### inc/Smartling/WP/Controller/PostBasedWidgetControllerStd.php
`- public function register()`

`+ public function register(): void`

`- public function save($post_id)`

`+ public function save($post_id): void`

### inc/Smartling/WP/Controller/ShortcodeForm.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/SubmissionsPageController.php
`- public function getQueue()`

`- public function setQueue($queue)`

`+ public function __construct(LocalizationPluginProxyInterface $connector, PluginInfo $pluginInfo, EntityHelper $entityHelper, SubmissionManager $manager, Cache $cache, Queue $queue, JobManager $jobInformationManager)`

`- public function register()`

`+ public function register(): void`

`- public function menu()`

`+ public function menu(): void`

`- public function renderPage()`

`+ public function renderPage(): void`

### inc/Smartling/WP/Controller/TaxonomyLinksController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/TaxonomyWidgetController.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Controller/TranslationLockController.php
`- public function getContentHelper()`

`- public function setContentHelper($contentHelper)`

`+ public function __construct(`

`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Table/MediaAttachmentTableWidget.php
`- public function __construct(MediaAttachmentRulesManager $manager)`

`+ public function __construct(MediaAttachmentRulesManager $manager, ReplacerFactory $replacerFactory)`

`- public function applyRowActions($item, $id)`

`+ public function applyRowActions(string $item, string $id): string`

`- public function get_columns()`

`+ public function get_columns(): array`

`- public function renderNewButton()`

`+ public function renderNewButton(): string`

`- public function prepare_items()`

`+ public function prepare_items(): void`

### inc/Smartling/WP/Table/QueueManagerTableWidget.php
`- public function register()`

`+ public function register(): void`

### inc/Smartling/WP/Table/SubmissionTableWidget.php
`- public function getLogger()`

`+ public function getLogger(): LoggerInterface`

`- public function getQueue()`

`- public function setQueue($queue)`

`- public function __construct(SubmissionManager $manager, EntityHelper $entityHelper, Queue $queue)`

`+ public function __construct(SubmissionManager $manager, EntityHelper $entityHelper, Queue $queue, JobManager $jobInformationManager)`

`- public function getSortingOptions($fieldNameKey = 'orderby', $orderDirectionKey = 'order')`

`+ public function getSortingOptions(string $fieldNameKey = 'orderby', string $orderDirectionKey = 'order'): array`

`- public function column_cb($item)`

`+ public function column_cb($item): string`

`- public function get_columns()`

`+ public function get_columns(): array`

`- public function get_sortable_columns()`

`+ public function get_sortable_columns(): array`

`- public function get_bulk_actions()`

`+ public function get_bulk_actions(): array`

`- public function prepare_items()`

`+ public function prepare_items(): void`

`- public function statusSelectRender()`

`+ public function statusSelectRender(): string`

`- public function outdatedStateSelectRender()`

`+ public function outdatedStateSelectRender(): string`

`- public function lockedStateSelectRender()`

`+ public function lockedStateSelectRender(): string`

`- public function clonedStateSelectRender()`

`+ public function clonedStateSelectRender(): string`

`- public function renderSearchBox()`

`+ public function renderSearchBox(): string`

`- public function targetLocaleSelectRender()`

`+ public function targetLocaleSelectRender(): string`

`- public function contentTypeSelectRender()`

`+ public function contentTypeSelectRender(): string`

`- public function renderSubmitButton($label)`

`+ public function renderSubmitButton(string $label): string`

### inc/Smartling/WP/WPHookInterface.php
`- public function register();`

`+ public function register(): void;`

### inc/Smartling/WP/i18n.php
`- public function register()`

`+ public function register(): void`
