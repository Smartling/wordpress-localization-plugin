<?php

namespace Smartling\FTS;

use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Smartling\AuthApi\AuthTokenProvider;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\Vendor\Smartling\FileTranslations\Params\TranslateFileParameters;
use Smartling\Vendor\Smartling\Project\ProjectApi;

/**
 * FTS (Fast Translation Service) API Wrapper
 *
 * Handles API communication with Smartling's File Translations API
 * for instant translation functionality. All requests include the
 * required X-SL-ServiceOrigin: wordpress header.
 */
class FtsApiWrapper
{
    use LoggerSafeTrait;

    private SettingsManager $settingsManager;
    private string $pluginName;
    private string $pluginVersion;

    /**
     * Cache for account UIDs by project ID
     *
     * @var array
     */
    private array $accountUidCache = [];

    public function __construct(
        SettingsManager $settingsManager,
        string $pluginName,
        string $pluginVersion
    ) {
        $this->settingsManager = $settingsManager;
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * Get configuration profile for submission
     *
     * @throws SmartlingDbException
     */
    private function getConfigurationProfile(SubmissionEntity $submission): ConfigurationProfileEntity
    {
        return $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
    }

    /**
     * Get account UID for a project
     *
     * Uses ProjectApi to fetch account UID and caches it for subsequent calls.
     *
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function getAccountUid(ConfigurationProfileEntity $profile): string
    {
        $projectId = $profile->getProjectId();

        // Return cached value if available
        if (isset($this->accountUidCache[$projectId])) {
            return $this->accountUidCache[$projectId];
        }

        AuthTokenProvider::setCurrentClientId($this->pluginName);
        AuthTokenProvider::setCurrentClientVersion($this->pluginVersion);

        $authProvider = AuthTokenProvider::create(
            $profile->getUserIdentifier(),
            $profile->getSecretKey(),
            $this->getLogger()
        );

        $projectApi = ProjectApi::create($authProvider, $projectId, $this->getLogger());
        $projectDetails = $projectApi->getProjectDetails();

        $accountUid = $projectDetails['accountUid'] ?? null;

        if (empty($accountUid)) {
            throw new \RuntimeException('Failed to get account UID from project details');
        }

        // Cache for future use
        $this->accountUidCache[$projectId] = $accountUid;

        $this->getLogger()->info('Retrieved account UID', [
            'projectId' => $projectId,
            'accountUid' => $accountUid,
        ]);

        return $accountUid;
    }

    /**
     * Create File Translations API client with required headers
     *
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    private function getFileTranslationsApi(ConfigurationProfileEntity $profile): FileTranslationsApiExtended
    {
        AuthTokenProvider::setCurrentClientId($this->pluginName);
        AuthTokenProvider::setCurrentClientVersion($this->pluginVersion);

        $authProvider = AuthTokenProvider::create(
            $profile->getUserIdentifier(),
            $profile->getSecretKey(),
            $this->getLogger()
        );

        $accountUid = $this->getAccountUid($profile);

        return FileTranslationsApiExtended::create($authProvider, $accountUid, $this->getLogger());
    }

    /**
     * Upload file for instant translation
     *
     * @param SubmissionEntity $submission
     * @param string $filePath Path to temporary file containing XML content
     * @param string $fileName Logical file name
     * @param string $fileType File type (xml, json, etc.)
     * @return array Response containing fileUid
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function uploadFile(
        SubmissionEntity $submission,
        string $filePath,
        string $fileName,
        string $fileType = 'xml'
    ): array {
        $profile = $this->getConfigurationProfile($submission);
        $api = $this->getFileTranslationsApi($profile);

        $this->getLogger()->info('Uploading file for instant translation', [
            'submissionId' => $submission->getId(),
            'fileName' => $fileName,
            'fileType' => $fileType,
        ]);

        return $api->uploadFile($filePath, $fileName, $fileType);
    }

    /**
     * Submit file for instant translation
     *
     * @param SubmissionEntity $submission
     * @param string $fileUid File UID returned from uploadFile
     * @param string $sourceLocaleId Source locale ID
     * @param array $targetLocaleIds Array of target locale IDs
     * @return array Response containing mtUid (machine translation UID)
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function submitForInstantTranslation(
        SubmissionEntity $submission,
        string $fileUid,
        string $sourceLocaleId,
        array $targetLocaleIds
    ): array {
        $profile = $this->getConfigurationProfile($submission);
        $api = $this->getFileTranslationsApi($profile);

        // Create translation parameters
        $params = new TranslateFileParameters();
        $params->setSourceLocaleId($sourceLocaleId);
        $params->setTargetLocaleIds($targetLocaleIds);

        $this->getLogger()->info('Submitting file for instant translation', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'sourceLocale' => $sourceLocaleId,
            'targetLocales' => $targetLocaleIds,
        ]);

        return $api->translateFile($fileUid, $params);
    }

    /**
     * Poll translation status
     *
     * @param SubmissionEntity $submission
     * @param string $fileUid File UID
     * @param string $mtUid Machine translation UID returned from submitForInstantTranslation
     * @return array Response containing translation progress and state
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function pollTranslationStatus(
        SubmissionEntity $submission,
        string $fileUid,
        string $mtUid
    ): array {
        $profile = $this->getConfigurationProfile($submission);
        $api = $this->getFileTranslationsApi($profile);

        return $api->getTranslationProgress($fileUid, $mtUid);
    }

    /**
     * Download translated file
     *
     * @param SubmissionEntity $submission
     * @param string $fileUid File UID
     * @param string $mtUid Machine translation UID
     * @param string $localeId Target locale ID
     * @return string Raw translated file content
     * @throws SmartlingApiException
     * @throws SmartlingDbException
     */
    public function downloadTranslatedFile(
        SubmissionEntity $submission,
        string $fileUid,
        string $mtUid,
        string $localeId
    ): string {
        $profile = $this->getConfigurationProfile($submission);
        $api = $this->getFileTranslationsApi($profile);

        $this->getLogger()->info('Downloading translated file', [
            'submissionId' => $submission->getId(),
            'fileUid' => $fileUid,
            'mtUid' => $mtUid,
            'localeId' => $localeId,
        ]);

        return $api->downloadTranslatedFile($fileUid, $mtUid, $localeId);
    }
}
