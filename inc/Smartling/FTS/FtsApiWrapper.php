<?php

namespace Smartling\FTS;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Smartling\AuthApi\AuthTokenProvider;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\Vendor\Smartling\FileTranslations\Params\TranslateFileParameters;
use Smartling\Vendor\Smartling\Project\ProjectApi;

class FtsApiWrapper
{
    use LoggerSafeTrait;

    private array $accountUidCache = [];

    public function __construct(
        private ApiWrapperInterface $apiWrapper,
        private SettingsManager $settingsManager,
        private string $pluginName,
        private string $pluginVersion,
    ) {
    }

    /**
     * @throws SmartlingDbException
     */
    private function getConfigurationProfile(SubmissionEntity $submission): ConfigurationProfileEntity
    {
        return $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
    }

    private function getFileTranslationsApi(ConfigurationProfileEntity $profile): FileTranslationsApiExtended
    {
        AuthTokenProvider::setCurrentClientId($this->pluginName);
        AuthTokenProvider::setCurrentClientVersion($this->pluginVersion);

        $authProvider = AuthTokenProvider::create(
            $profile->getUserIdentifier(),
            $profile->getSecretKey(),
            $this->getLogger(),
        );

        return FileTranslationsApiExtended::create(
            $authProvider,
            $this->apiWrapper->getAccountUid($profile),
            $this->getLogger(),
        );
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function uploadFile(
        SubmissionEntity $submission,
        string $filePath,
        string $fileName,
        string $fileType = 'xml',
    ): string {
        $this->getLogger()->info("Uploading file for instant translation, submissionId={$submission->getId()}, fileType=$fileType, fileName=$fileName");

        $fileUid = $this->getFileTranslationsApi($this->getConfigurationProfile($submission))
            ->uploadFile($filePath, $fileName, $fileType)['fileUid'] ?? null;

        if (empty($fileUid)) {
            throw new \RuntimeException('Failed to get fileUid from upload response');
        }
        return $fileUid;
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function submitForInstantTranslation(
        SubmissionEntity $submission,
        string $fileUid,
        string $sourceLocaleId,
        array $targetLocaleIds,
    ): string {
        $params = new TranslateFileParameters();
        $params->setSourceLocaleId($sourceLocaleId);
        $params->setTargetLocaleIds($targetLocaleIds);

        $this->getLogger()->info("Submitting file for instant translation, submissionId={$submission->getId()}, fileUid=$fileUid, sourceLocaleId=$sourceLocaleId, targetLocaleIds=" . implode(',', $targetLocaleIds));

        $mtUid = $this->getFileTranslationsApi($this->getConfigurationProfile($submission))
            ->translateFile($fileUid, $params)['mtUid'] ?? null;
        if (empty($mtUid)) {
            throw new \RuntimeException('Failed to get mtUid from translation response');
        }

        return $mtUid;
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function pollTranslationStatus(
        SubmissionEntity $submission,
        string $fileUid,
        string $mtUid,
    ): array {
        return $this->getFileTranslationsApi($this->getConfigurationProfile($submission))
            ->getTranslationProgress($fileUid, $mtUid);
    }

    /**
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function downloadTranslatedFile(
        SubmissionEntity $submission,
        string $fileUid,
        string $mtUid,
        string $localeId,
    ): string {
        $this->getLogger()->info("Downloading translated file, submissionId={$submission->getId()}, fileUid=$fileUid, mtUid=$mtUid, localeId=$localeId");

        return $this->getFileTranslationsApi($this->getConfigurationProfile($submission))
            ->downloadTranslatedFile($fileUid, $mtUid, $localeId);
    }
}
