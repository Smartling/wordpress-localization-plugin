<?php

namespace Smartling;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\DecodedTranslation;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\XmlHelper;
use Smartling\Models\AssetUid;

class RestApi {
    use DITrait;
    use LoggerSafeTrait;

    private const PARAMETER_ASSET_UID = 'id';
    private const PARAMETER_LOCALE = 'locale';
    private const PARAMETER_PROJECT_UID = 'projectUid';
    private const ROUTE_NAMESPACE = 'smartling-connector/v2';
    private const ROUTE_REGEX_ASSET_UID = '(?P<' . self::PARAMETER_ASSET_UID . '>[a-z-_]+-\d+)';
    private const ROUTE_REGEX_PROJECT_UID = '(?P<' . self::PARAMETER_PROJECT_UID . '>[a-z0-9]+)';

    private XmlHelper $xmlHelper;

    public function __construct()
    {
        $this->xmlHelper = $this->fromContainer('helper.xml');
    }

    public function initApi(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route(
                self::ROUTE_NAMESPACE,
                'projects/' . self::ROUTE_REGEX_PROJECT_UID . '/assets',
                [
                    'methods' => 'POST',
                    'callback' => function (\WP_REST_Request $request) {
                        $request = $this->setPrettyPrint($request);
                        $parameters = json_decode($request->get_body(), true, 512, JSON_THROW_ON_ERROR);
                        $nextPageToken = $parameters['nextPageToken'] ?? 0;
                        $result = null;
                    },
                    'permission_callback' => [$this, 'permissionCallbackRead'],
                ]
            );
        });
        add_action('rest_api_init', function () {
            register_rest_route(
                self::ROUTE_NAMESPACE,
                'projects/' . self::ROUTE_REGEX_PROJECT_UID .
                '/assets/' . self::ROUTE_REGEX_ASSET_UID .
                '/locales/(?P<' . self::PARAMETER_LOCALE . '>[a-z-]+)',
                [
                    'methods' => 'POST',
                    'callback' => function (\WP_REST_Request $request) {
                        $request = $this->setPrettyPrint($request);
                        $file = $request->get_file_params()['file'] ?? null;
                        if ($file === null) {
                            return new \WP_REST_Response('File required', 400);
                        }
                        $targetAssetUid = $this->getAssetUid($request->get_body_params()['targetAssetUid'] ?? '');
                        if ($targetAssetUid instanceof \WP_REST_Response) {
                            return $targetAssetUid;
                        }
                        $assetUid = $this->getAssetUid($request->get_param(self::PARAMETER_ASSET_UID));
                        if ($assetUid instanceof \WP_REST_Response) {
                            return $assetUid;
                        }
                        try {
                            (new ContentProvider())->applyTranslation(
                                $request->get_param(self::PARAMETER_PROJECT_UID),
                                $assetUid,
                                $targetAssetUid,
                                $request->get_param('locale'),
                                $this->getTranslation($file['tmp_name'], $file['type']),
                            );
                        } catch (EntityNotFoundException|\InvalidArgumentException|\RuntimeException $e) {
                            return new \WP_REST_Response($e->getMessage(), $e->getCode());
                        }

                        return new \WP_REST_Response(
                            ['response' => ':)'],
                        );
                    },
                    'permission_callback' => [$this, 'permissionCallbackWrite'],
                ]
            );
            register_rest_route(
                self::ROUTE_NAMESPACE,
                'projects/' . self::ROUTE_REGEX_PROJECT_UID . '/assets/' . self::ROUTE_REGEX_ASSET_UID . '/placeholders',
                [
                    'methods' => 'POST',
                    'callback' => function (\WP_REST_Request $request) {
                        $request = $this->setPrettyPrint($request);
                        $assetUid = $this->getAssetUid($request->get_param('id'));
                        if ($assetUid instanceof \WP_REST_Response) {
                            return $assetUid;
                        }
                        try {
                            $targetLocales = json_decode($request->get_body_params()['smartlingLocaleIds'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                            if (!is_array($targetLocales)) {
                                throw new \InvalidArgumentException('Target locales array required');
                            }
                        } catch (\InvalidArgumentException|\JsonException $e) {
                            return new \WP_REST_Response($e->getMessage(), 400);
                        }

                        return new \WP_REST_Response($this->wrapSuccessResponse([
                                'items' => (new ContentProvider())->createPlaceholders(
                                    $request->get_param(self::PARAMETER_PROJECT_UID),
                                    $assetUid,
                                    $targetLocales
                                ),
                            ]
                        ));
                    },
                    'permission_callback' => [$this, 'permissionCallbackWrite'],
                ]
            );
            register_rest_route(self::ROUTE_NAMESPACE, 'assets/' . self::ROUTE_REGEX_ASSET_UID . '/raw', [
                'methods' => 'GET',
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    $assetUid = $this->getAssetUid($request->get_param('id'));
                    if ($assetUid instanceof \WP_REST_Response) {
                        return $assetUid;
                    }

                    try {
                        return (new ContentProvider())->getRawContent($assetUid);
                    } catch (EntityNotFoundException|SmartlingInvalidFactoryArgumentException $e) {
                        return new \WP_REST_Response($e->getMessage(), 404);
                    }
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
        });
    }

    public static function permissionCallbackRead(): bool
    {
        return current_user_can('read');
    }

    public static function permissionCallbackWrite(): bool
    {
        return current_user_can('publish_pages');
    }

    private function getAssetUid(string $string): AssetUid|\WP_REST_Response
    {
        try {
            return AssetUid::fromString($string);
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response($e->getMessage(), 400);
        }
    }

    private function getTranslation(string $filePath, string $fileType): DecodedTranslation|\WP_REST_Response
    {
        $contents = file_get_contents($filePath);
        if (!$contents) {
            $this->getLogger()->error("Unable to open localPath=$filePath for reading");
            throw new \RuntimeException('', 500);
        }

        switch ($fileType) {
            case 'application/xml':
                return $this->xmlHelper->decode($contents);
            case 'json':
                try {
                    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR)['fields'] ?? [];
                } catch (\JsonException $e) {
                    return new \WP_REST_Response($e->getMessage(), 400);
                }
            default:
                return NEW \WP_REST_Response("Unsupported fileType=$fileType", 400);
        }
    }

    private function setPrettyPrint(\WP_REST_Request $request): \WP_REST_Request
    {
        $request->set_param('_pretty', true);

        return $request;
    }

    private function wrapSuccessResponse(mixed $response): array
    {
        return [
            'code' => 'SUCCESS',
            'data' => $response,
        ];
    }
}
