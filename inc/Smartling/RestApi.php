<?php

namespace Smartling;

use Smartling\DbAl\WordpressContentEntities\EntityWithMetadata;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Processors\ContentEntitiesIOFactory;

class RestApi
{
    use DITrait;

    private ContentEntitiesIOFactory $contentEntitiesIOFactory;
    private ContentHelper $contentHelper;
    private WordpressFunctionProxyHelper $wordpressProxy;

    public function __construct()
    {
        $this->contentEntitiesIOFactory = $this->fromContainer('factory.contentIO');
        $this->contentHelper = $this->fromContainer('content.helper');
        $this->wordpressProxy = $this->fromContainer('wp.proxy');
    }

    public function initApi(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('smartling-connector/v2', 'assets/(?P<id>[a-z-_]+-\d+)/raw', [
                'callback' => function (\WP_REST_Request $request) {
                    $this->registerHandlers();
                    $parts = explode("-", $request->get_param('id'));
                    $id = array_pop($parts);
                    try {
                        $entity = $this->contentHelper->getWrapper(implode("-", $parts))->get($id);
                    } catch (EntityNotFoundException|SmartlingInvalidFactoryArgumentException $e) {
                        return new \WP_REST_Response($e->getMessage(), 404);
                    }
                    $result = [
                        'entity' => $entity->toArray(),
                    ];
                    if ($entity instanceof EntityWithMetadata) {
                        $result['meta'] = $entity->getMetadata();
                    }
                    return $result;
                },
                'methods' => 'GET',
                'permission_callback' => [$this, 'permissionCallback'],
            ]);
        });
    }

    public static function permissionCallback(): bool
    {
        return true; // TODO
    }

    private function registerHandlers(): void
    {
        foreach ($this->wordpressProxy->get_taxonomies() as $taxonomy) {
            $this->contentEntitiesIOFactory->registerHandler($taxonomy, new TaxonomyEntityStd($taxonomy));
        }
        foreach ($this->wordpressProxy->get_post_types() as $postType) {
            $this->contentEntitiesIOFactory->registerHandler($postType, new PostEntityStd($postType));
        }
        $this->fromContainer('manager.content.external'); // Has side effect of registering external content handlers
    }
}
