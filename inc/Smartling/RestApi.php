<?php

namespace Smartling;

use Smartling\Base\SmartlingCore;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Models\Content;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionManager;

class RestApi extends \WP_REST_Controller {
    use DITrait;

    private const ASSET_UID_REGEX = '(?P<id>[a-z-_]+-\d+)';
    private const NAMESPACE = 'smartling-connector/v2';

    private ContentEntitiesIOFactory $contentEntitiesIOFactory;
    private ContentHelper $contentHelper;
    private FileUriHelper $fileUriHelper;
    private SmartlingCore $core;
    private SubmissionManager $submissionManager;
    private WordpressFunctionProxyHelper $wordpressProxy;

    public function __construct()
    {
        $this->contentEntitiesIOFactory = $this->fromContainer('factory.contentIO');
        $this->contentHelper = $this->fromContainer('content.helper');
        $this->core = $this->fromContainer('entrypoint');
        $this->fileUriHelper = $this->fromContainer('file.uri.helper');
        $this->submissionManager = $this->fromContainer('manager.submission');
        $this->wordpressProxy = $this->fromContainer('wp.proxy');
    }

    public function register_routes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route(self::NAMESPACE, 'assets/' . self::ASSET_UID_REGEX . '/raw', [
                'methods' => 'GET',
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    try {
                        return ($this->getProvider())->getRawContent($this->getAssetUid($request->get_param('id')));
                    } catch (EntityNotFoundException|SmartlingInvalidFactoryArgumentException $e) {
                        return new \WP_REST_Response($e->getMessage(), 404);
                    }
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
            register_rest_route(self::NAMESPACE, 'assets', [
                'methods' => 'GET',
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    return [
                        'items' => $this->getProvider()->getAssets(
                            $request->get_param('assetType'),
                            $request->get_param('searchTerm'),
                            $request->get_param('limit'),
                            $request->get_param('sortBy'),
                            $request->get_param('orderBy'),
                        ),
                        'nextPageToken' => '', // TODO discuss
                    ];
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
            register_rest_route(self::NAMESPACE, 'assets/' . self::ASSET_UID_REGEX . '/details', [
                'methods' => 'GET',
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    try {
                        return $this->getProvider()->getAsset($this->getAssetUid($request->get_param('id')));
                    } catch (\Throwable $e) {
                        return $this->errorResponse($e);
                    }
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
            register_rest_route(self::NAMESPACE, 'assets/' . self::ASSET_UID_REGEX . '/related', [
                'methods' => 'POST',
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    try {
                        $parsed = json_decode($request->get_body(), true, 3, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        return new \WP_REST_Response($e->getMessage(), 400);
                    }

                    $validationResult = rest_validate_value_from_schema($parsed, [
                        'properties' => [
                            'children' => [
                                'properties' => [
                                    'depth' => ['type' => 'integer', 'minimum' => 0],
                                    'include' => ['type' => 'boolean'],
                                ],
                                'required' => ['depth', 'include'],
                                'type' => 'object',
                            ],
                            'essential' => [
                                'properties' => [
                                    'include' => ['type' => 'boolean'],
                                ],
                                'required' => ['include'],
                                'type' => 'object',
                            ],
                            'limit' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'nextPageToken' => ['type' => 'string'],
                            'required' => [
                                'properties' => [
                                    'depth' => ['type' => 'integer', 'minimum' => 0],
                                    'include' => ['type' => 'boolean'],
                                ],
                                'required' => ['depth', 'include'],
                                'type' => 'object',
                            ],
                        ],
                        'required' => ['children', 'essential', 'limit', 'related'],
                        'type' => 'object',
                    ]);
                    if ($validationResult !== true) {
                        return $validationResult;
                    }
                    try {
                        return $this->getProvider()->getRelatedAssets(
                            $this->getAssetUid($request->get_param('id')),
                            $parsed['limit'],
                            $parsed['essential']['include'],
                            ($parsed['child']['include'] ?? false) ? $parsed['child']['depth'] ?? 0 : 0,
                            ($parsed['related']['include'] ?? false) ? $parsed['related']['depth'] ?? 0 : 0,
                        );
                    } catch (\Throwable $e) {
                        return $this->errorResponse($e);
                    }
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
            register_rest_route(self::NAMESPACE, 'assets/' . self::ASSET_UID_REGEX . '/content/metadata', [
                'methods' => 'POST', // TODO discuss
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    return [];
                },
                'permission_callback' => [$this, 'permissionCallbackRead'],
            ]);
            register_rest_route(self::NAMESPACE, 'assets/' . self::ASSET_UID_REGEX . '/content/body', [
                'methods' => 'POST', // TODO discuss
                'callback' => function (\WP_REST_Request $request) {
                    $request = $this->setPrettyPrint($request);
                    try {
                        return $this->getProvider()->getRawContent($this->getAssetUid($request->get_param('id')));
                    } catch (\Throwable $e) {
                        return $this->errorResponse($e);
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

    private function getAssetUid(string $string): Content
    {
        $parts = explode('-', $string);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('AssetUid string expected to be contentType-id');
        }
        $id = array_pop($parts);

        return new Content($id, implode('-', $parts));
    }

    /**
     * @throws \Throwable
     */
    private function errorResponse(\Throwable $e): \WP_REST_Response
    {
        if ($e instanceof \InvalidArgumentException || $e instanceof \JsonException) {
            return new \WP_REST_Response($e->getMessage(), 400);
        }
        if ($e instanceof EntityNotFoundException || $e instanceof SmartlingInvalidFactoryArgumentException) {
            return new \WP_REST_Response($e->getMessage(), 404);
        }
        if ($e instanceof \TypeError) {
            return new \WP_REST_Response(preg_replace('~, called in .+~', '', $e->getMessage()), 400);
        }

        throw $e;
    }

    private function setPrettyPrint(\WP_REST_Request $request): \WP_REST_Request
    {
        $request->set_param('_pretty', true);

        return $request;
    }

    private function getProvider(): ContentProvider
    {
        return new ContentProvider(
            $this->contentHelper,
            $this->contentEntitiesIOFactory,
            $this->fileUriHelper,
            $this->core,
            $this->submissionManager,
            $this->wordpressProxy,
        );
    }
}
