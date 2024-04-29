<?php

namespace Smartling\API;

use Smartling\Models\AssetUid;
use Smartling\Vendor\Smartling\AuthApi\AuthApiInterface;
use Smartling\Vendor\Smartling\BaseApiAbstract;

class SubmissionServiceApi extends BaseApiAbstract {

    public const ASSET_UID_KEY = 'assetUid';
    private const BUCKET_NAME = 'wordpress';
    private const ENDPOINT_URL = 'https://api.smartling.com/submission-service-api/v3/projects';
    private const LIMIT = 10;

    public function __construct(AuthApiInterface $auth, string $projectId)
    {
        parent::__construct($projectId, self::initializeHttpClient(self::ENDPOINT_URL), $this->getLogger(), self::ENDPOINT_URL);
        $this->setAuth($auth);
    }

    public function createSubmission(AssetUid $assetUid, string $targetLocale, string $translationRequestUid): void
    {
        $this->sendRequest(
            'buckets/' . self::BUCKET_NAME . '/translation-submissions',
            $this->getDefaultRequestData('json', [
                'translationRequestUid' => $translationRequestUid,
                'submitterName' => self::BUCKET_NAME,
                "state" => "In Progress",
                "targetLocaleId" => $targetLocale,
                "targetAssetKey" => [self::ASSET_UID_KEY => (string)$assetUid],
            ]),
            self::HTTP_METHOD_POST,
        );
    }

    public function createTranslationRequest(string $assetUid, string $title, string $fileUri, string $contentHash, string $originalLocale): string
    {
        return $this->sendRequest(
            'buckets/' . self::BUCKET_NAME . '/translation-requests',
            $this->getDefaultRequestData('json', [
                'originalAssetKey' => [self::ASSET_UID_KEY => $assetUid],
                'title' => $title,
                'fileUri' => $fileUri,
                'contentHash' => $contentHash,
                'originalLocaleId' => $originalLocale,
            ]),
            self::HTTP_METHOD_POST,
        )['translationRequestUid'];
    }

    public function searchSubmissions(?array $assetUidStrings = null, ?string $targetLocale = null): \Generator
    {
        $offset = 0;

        $result = $this->getSearchSubmissions($offset, $assetUidStrings, $targetLocale);

        yield from $result;

        while (count($result) === self::LIMIT) {
            $offset += self::LIMIT;
            $result = $this->getSearchSubmissions($offset, $assetUidStrings, $targetLocale);
            yield from $result;
        }
    }

    private function getSearchSubmissions(int $offset, ?array $targetAssetKey, ?string $targetLocale): array
    {
        $parameters = [
            'limit' => self::LIMIT,
            'offset' => $offset,
        ];
        if ($targetAssetKey !== null) {
            $parameters['targetAssetKey'] = $targetAssetKey;
        }
        if ($targetLocale !== null) {
            $parameters['targetLocaleId'] = $targetLocale;
        }

        return $this->sendRequest(
            'buckets/' . self::BUCKET_NAME . '/search/translation-submissions',
            $this->getDefaultRequestData('json', $parameters),
            self::HTTP_METHOD_POST,
        )['items'];
    }

    public function searchTranslationPackages(string $sourceAssetKey, string $targetLocale): array
    {
        return $this->sendRequest(
            'buckets/' . self::BUCKET_NAME . '/search/translation-packages',
            $this->getDefaultRequestData('json', [
                'limit' => self::LIMIT,
                'originalAssetKey' => [self::ASSET_UID_KEY => $sourceAssetKey],
                'targetLocaleId' => $targetLocale,
            ]),
            self::HTTP_METHOD_POST,
        );
    }

    public function searchTranslationRequests(array $originalAssetKey): \Generator
    {
        $offset = 0;

        $result = $this->getSearchTranslationRequests($offset, $originalAssetKey);

        yield from $result;

        while (count($result) === self::LIMIT) {
            $offset += self::LIMIT;
            $result = $this->getSearchTranslationRequests($offset, $originalAssetKey);
            yield from $result;
        }
    }

    private function getSearchTranslationRequests(int $offset, array $originalAssetKey): array
    {
        return $this->sendRequest(
            'buckets/' . self::BUCKET_NAME . '/search/translation-requests',
            $this->getDefaultRequestData('json', [
                'limit' => self::LIMIT,
                'offset' => $offset,
                'originalAssetKey' => $originalAssetKey,
            ]),
            self::HTTP_METHOD_POST,
        )['items'];
    }
}
