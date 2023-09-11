<?php

namespace Smartling;

use Smartling\ContentTypes\ExternalContentManager;
use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\DbAl\WordpressContentEntities\EntityWithMetadata;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\DecodedTranslation;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\AssetUid;
use Smartling\Models\Settings;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Services\ContentRelationsDiscoveryService;
use Smartling\Submissions\Submission;
use Smartling\Submissions\SubmissionSimple;

class ContentProvider {
    use DITrait;
    use LoggerSafeTrait;

    private ApiWrapperInterface $apiWrapper;
    private ContentEntitiesIOFactory $contentEntitiesIOFactory;
    private ContentHelper $contentHelper;
    private ContentRelationsDiscoveryService $contentRelationsDiscoveryService;
    private ExternalContentManager $externalContentManager;
    private FieldsFilterHelper $fieldsFilterHelper;
    private PostContentHelper $postContentHelper;
    private WordpressFunctionProxyHelper $wordpressProxy;

    public function __construct()
    {
        $this->apiWrapper = $this->fromContainer('api.wrapper.with.retries');
        $this->contentEntitiesIOFactory = $this->fromContainer('factory.contentIO');
        $this->contentHelper = $this->fromContainer('content.helper');
        $this->contentRelationsDiscoveryService = $this->fromContainer('service.relations-discovery');
        $this->externalContentManager = $this->fromContainer('manager.content.external');
        $this->fieldsFilterHelper = $this->fromContainer('fields-filter.helper');
        $this->postContentHelper = $this->fromContainer('helper.post.content');
        $this->wordpressProxy = $this->fromContainer('wp.proxy');
    }

    /**
     * @throws EntityNotFoundException
     */
    public function applyTranslation(string $projectUid, AssetUid $sourceAssetUid, AssetUid $targetAssetUid, string $targetLocale, DecodedTranslation $decoded): void
    {
        $this->addHandlersForRegisteredTypes();
        $sourceBlogId = $this->wordpressProxy->get_current_blog_id();
        $settings = Settings::fromArray($this->apiWrapper->getSettings($projectUid)['settings']);
        $targetBlogId = $this->getTargetBlogId($settings, $targetLocale);
        $submission = new SubmissionSimple($sourceAssetUid->getContentType(), $sourceBlogId, $sourceAssetUid->getId(), $targetBlogId, $targetAssetUid->getId());
        $relations = [];
        foreach (
            $this->contentRelationsDiscoveryService
                ->getRelations($sourceAssetUid->getContentType(), $sourceAssetUid->getId(), [])
                ->getOriginalReferences() as $contentType => $ids
        ) {
            foreach ($ids as $id) {
                $relations[] = $contentType . '-' . $id;
            }
        }
        $translationRequests = iterator_to_array($this->apiWrapper->searchTranslationRequests($projectUid, $relations));
        $translatedAssets = iterator_to_array($this->apiWrapper->searchSubmissions($projectUid, targetLocale: $targetLocale, translationRequestIds: $translationRequests));

        #region process translation
        $translation = $decoded->getTranslatedFields();
        $original = $decoded->getOriginalFields();
        $translatedValues = $this->fieldsFilterHelper->structurizeArray($translation);

        #region applyTranslatedValues
        $originalValues = $this->fieldsFilterHelper->prepareSourceData($original);
        $originalValues = $this->fieldsFilterHelper->flattenArray($originalValues);

        $translatedValues = $this->fieldsFilterHelper->prepareSourceData($translatedValues);
        $translatedValues = $this->fieldsFilterHelper->flattenArray($translatedValues);

        // TODO apply translated values filters

        $result = array_merge($originalValues, $translatedValues);

        $translation = $this->fieldsFilterHelper->structurizeArray($result);
        $translation = $this->fieldsFilterHelper->processStringsAfterDecoding($translation);
        #endregion

        if (!array_key_exists('meta', $translation)) {
            $translation['meta'] = [];
        }
        $targetContent = $this->contentHelper->getEntity($targetBlogId, $targetAssetUid);
        #region process post content blocks and locking
        if (array_key_exists('entity', $translation) && ArrayHelper::notEmpty($translation['entity'])) {
            $targetContentArray = $targetContent->toArray();
            if (array_key_exists('post_content', $translation['entity']) && array_key_exists('post_content', $targetContentArray)) {
                $translation['entity']['post_content'] = $this->postContentHelper->applyBlockLevelLocks(
                    $targetContentArray['post_content'],
                    $this->postContentHelper->replacePostTranslate($original['entity']['post_content'] ?? '', $translation['entity']['post_content']),
                );
            }
            // TODO locking
        }
        #endregion

        $translation = $this->externalContentManager->setExternalContent(
            $decoded->getOriginalFields(),
            $translation,
            $submission,
        );
        if ($targetContent instanceof EntityAbstract) {
            foreach ($translation['entity'] ?? [] as $key => $value) {
                $targetContent->$key = $value;
            }
        } else {
            $targetContent = $targetContent->fromArray($translation['entity']);
        }
        // TODO completion percentages, status changes,
        $this->contentHelper->writeTargetContent($submission, $targetContent);
        $this->setObjectTerms($submission);
        if (array_key_exists('meta', $translation) && ArrayHelper::notEmpty($translation['meta'])) {
            $metaFields = $translation['meta'];
            // TODO clean target metadata based on settings, locking
            $this->contentHelper->writeTargetMetadata($submission, $metaFields);
        }
        #endregion
    }

    public function createPlaceholders(string $projectUid, AssetUid $assetUid, array $targetLocales): array
    {
        $this->addHandlersForRegisteredTypes();
        $settings = Settings::fromArray($this->apiWrapper->getSettings($projectUid)['settings']);
        $entity = $this->contentHelper->getEntity($settings->getSourceBlogId(), $assetUid);
        $translationRequests = $this->apiWrapper->searchTranslationRequests($projectUid, [(string)$assetUid]);
        if (count($translationRequests) === 0) {
            $translationRequestUid = $this->apiWrapper->createTranslationRequest($projectUid, (string)$assetUid, $entity->getTitle(), "/$assetUid.xml", "todo", $settings->getSourceLocale());
        } else {
            $translationRequestUid = $translationRequests[0]['translationRequestUid'];
        }
        $submissions = iterator_to_array($this->apiWrapper->searchSubmissions($projectUid, [$assetUid]));

        $result = [];
        foreach ($targetLocales as $targetLocale) {
            $targetBlogId = $this->getTargetBlogId($settings, $targetLocale);

            $target = null;
            foreach ($submissions as $submission) {
                if ($submission['targetLocaleId'] === $targetLocale) {
                    $target = AssetUid::fromString($submission['targetAssetKey']['assetUid']);
                    try {
                        $target = $this->contentHelper->getEntity($targetBlogId, $target);
                        $this->getLogger()->debug("Skip create new placeholders, assetUid=$assetUid already exists in targetBlogId=$targetBlogId");
                    } catch (\Exception) {
                        $target = null;
                    }

                }
            }
            $content = $target === null ? $entity->forInsert() : $entity;
            if ($target === null) {
                $this->getLogger()->debug("Preparing target entity for assetUid=$assetUid, targetBlogId=$targetBlogId.");
                $target = new AssetUid(
                    $assetUid->getContentType(),
                    $this->contentHelper->setContent($targetBlogId, $assetUid, $content)->getId(),
                );
                $this->apiWrapper->createSubmission($projectUid, $translationRequestUid, $target, $targetLocale);
            } else {
                $this->getLogger()->debug("Rewriting content for assetUid=$assetUid, targetBlogId=$targetBlogId");
                $this->contentHelper->setContent($targetBlogId, $assetUid, $content->setId($target->getId()));
            }

            $result[] = [
                'sourceAssetId' => (string)$assetUid,
                'targetAssetId' => (string)$target,
                'smartlingLocaleId' => $targetLocale,
            ];
        }

        return $result;
    }

    public function getRawContent(AssetUid $assetUid): array
    {
        $this->addHandlersForRegisteredTypes();
        $entity = $this->contentHelper->getWrapper($assetUid->getContentType())->get($assetUid->getId());
        $result = [
            'entity' => $entity->toArray(),
        ];
        if ($entity instanceof EntityWithMetadata) {
            $result['meta'] = $entity->getMetadata();
        }

        return $result;
    }

    private function addHandlersForRegisteredTypes(): void
    {
        foreach ($this->wordpressProxy->get_taxonomies() as $taxonomy) {
            $this->contentEntitiesIOFactory->registerHandler($taxonomy, new TaxonomyEntityStd($taxonomy));
        }
        foreach ($this->wordpressProxy->get_post_types() as $postType) {
            $this->contentEntitiesIOFactory->registerHandler($postType, new PostEntityStd($postType));
        }
    }

    private function getTargetBlogId(Settings $settings, string $targetLocale): int
    {
        foreach ($settings->getTargetLocales() as $locale) {
            if ($locale['smartlingLocale'] === $targetLocale) {
                return $locale['blogId'];
            }
        }

        throw new SmartlingConfigException('Unable to find target blog for locale=' . $targetLocale);
    }

    private function setObjectTerms(Submission $submission): void
    {

    }
}
