<?php

namespace Smartling\Services;

use Exception;
use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Models\UserCloneRequest;
use Smartling\Models\UserTranslationRequest;

/**
 *
 * ajax service that discovers related items.
 * usage: GET /wp-admin/admin-ajax.php?action=smartling-get-relations&id=48&content-type=post&targetBlogIds=2,3,4,5
 *
 * Response Example:
 *
 * {
 *  "status":"SUCCESS",
 *  "response":{
 *      "data":{
 *          "originalReferences":{
 *              "attachment":[244,232,26,231],
 *              "post":[1],
 *              "page":[2],
 *              "post_tag":[13,14],
 *              "category":[1]
 *          }
 *      }
 *  }
 * }
 *
 *
 * blogId is discovered from current active blog via WordPress Multisite API
 */
class ContentRelationsHandler extends BaseAjaxServiceAbstract
{
    use LoggerSafeTrait;

    public const ACTION_NAME = 'smartling-get-relations';

    public const ACTION_NAME_CREATE_SUBMISSIONS = 'smartling-create-submissions';

    public const FORM_ACTION_CLONE = 'clone';
    public const FORM_ACTION_UPLOAD = 'upload';

    private ContentRelationsDiscoveryService $service;
    public function __construct(ContentRelationsDiscoveryService $service)
    {
        parent::__construct($_GET);
        $this->service = $service;
    }

    public function register(): void
    {
        parent::register();
        add_action('wp_ajax_' . static::ACTION_NAME_CREATE_SUBMISSIONS, [$this, 'createSubmissionsHandler'], 10, 0);
    }

    /**
     * Handler for POST request that creates submissions for main content and selected relations
     *
     * Request Example:
     *
     *  [
     *      'source'       => ['contentType' => 'post', 'id' => [0 => '48']],
     *      'job'          =>
     *      [
     *          'id'          => 'abcdef123456',
     *          'name'        => '',
     *          'description' => '',
     *          'dueDate'     => '',
     *          'timeZone'    => 'Europe/Kiev',
     *          'authorize'   => 'true',
     *      ],
     *      'targetBlogIds' => '3,2',
     *      'relations'    => {{@see actionHandler }} relations response
     *  ]
     */
    public function createSubmissionsHandler(array $data = null): void
    {
        if ($data === null) {
            $data = $_POST;
        }
        try {
            if ($data['formAction'] === self::FORM_ACTION_CLONE) {
                $this->service->clone(UserCloneRequest::fromArray($data));
            } else {
                $this->service->createSubmissions(UserTranslationRequest::fromArray($data));
            }
            $this->returnResponse(['status' => BaseAjaxServiceAbstract::RESPONSE_SUCCESS]);
        } catch (Exception $e) {
            $this->returnError('content.submission.failed', $e->getMessage());
        }
    }

    public function actionHandler(): void
    {
        $data = $_GET;
        $data['targetBlogIds'] = $this->convertTargetBlogIds($data['targetBlogIds']);
        try {
            $this->returnSuccess(['data' => $this->service->getRelations($data['content-type'], (int)$data['id'], $data['targetBlogIds'])->toArray()]);
        } catch (SmartlingHumanReadableException $e) {
            $this->returnError($e->getKey(), $e->getMessage(), $e->getResponseCode());
        } catch (Exception $e) {
            $this->returnError('', $e->getMessage());
        }
    }

    /**
     * @return int[]
     */
    private function convertTargetBlogIds(string $string): array
    {
        $blogs = array_unique(explode(',', $string));

        array_walk($blogs, static function (string $el) {
            return (int)$el;
        });

        return $blogs;
    }
}
