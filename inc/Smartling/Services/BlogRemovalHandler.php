<?php

namespace Smartling\Services;

use Smartling\ApiWrapperInterface;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/** @noinspection PhpUnused */
class BlogRemovalHandler implements WPHookInterface
{
    use LoggerSafeTrait;

    public function __construct(
        private ApiWrapperInterface $apiWrapper,
        private SettingsManager $settingsManager,
        private SubmissionManager $submissionManager,
    ) {}

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     */
    public function register(): void
    {
        add_action('delete_blog', [$this, 'blogRemovalHandler']);
        add_action('wp_delete_site', [$this, 'siteRemovalHandler']);
    }

    /**
     * At this time blog does not exist anymore
     * We need to remove all related submissions if any
     * And cleanup all
     */
    public function blogRemovalHandler(int $blogId): void
    {
        $submissions = $this->getSubmissions($blogId);

        if (0 < count($submissions)) {
            $this->getLogger()->info(
                vsprintf(
                    'While deleting blog id=%d found %d translations.', [$blogId, count($submissions)]
                )
            );

            foreach ($submissions as $submission)
            {
                $this->getLogger()->info(
                    vsprintf(
                        'Deleting submission id=%d that references deleted blog %d.', [$submission->getId(), $blogId]
                    )
                );
                $this->submissionManager->delete($submission);

                if ('' !== $submission->getFileUri() && 0 === $this->getSubmissionCountByFileUri($submission->getFileUri())) {
                    $this->getLogger()->notice(
                        vsprintf(
                            'File %s is not in use and will be deleted', [$submission->getFileUri()]
                        )
                    );
                    $this->apiWrapper->deleteFile($submission);
                }
            }
        }

        foreach ($this->settingsManager->getEntities() as $profile) {
            if ($profile->getOriginalBlogId()->getBlogId() === $blogId) {
                $this->settingsManager->deleteProfile($profile->getId());
                $this->getLogger()->notice("Deleted profile profileId={$profile->getId()} while deleting blogId=$blogId");
            } else {
                foreach ($profile->getTargetLocales() as $locale) {
                    if ($locale->getBlogId() === $blogId) {
                        $profile->setTargetLocales(array_filter($profile->getTargetLocales(), static function (Locale $locale) use ($blogId) {
                            return $locale->getBlogId() !== $blogId;
                        }));
                        $this->settingsManager->storeEntity($profile);
                        break;
                    }
                }
            }
        }
    }

    public function siteRemovalHandler(\WP_Site $site): void
    {
        $this->blogRemovalHandler((int)$site->blog_id);
    }

    private function getSubmissions($targetBlogId): array
    {
        return $this->submissionManager->find([SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId]);
    }

    private function getSubmissionCountByFileUri($fileUri): int
    {
        return count($this->submissionManager->find([SubmissionEntity::FIELD_FILE_URI => $fileUri]));
    }
}
