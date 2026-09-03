<?php

namespace Smartling\Services;

use Exception;
use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Helpers\AjaxAuthorizationFailure;
use Smartling\Helpers\AjaxSecurityTrait;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\SmartlingUserCapabilities;
use Smartling\Helpers\WordpressFunctionProxyHelper;
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
 *          "references":[
 *              {"contentType":"attachment","id":244,"status":"new"},
 *              {"contentType":"attachment","id":232,"status":"completed"},
 *              {"contentType":"post","id":1,"status":"new"},
 *              {"contentType":"category","id":1,"status":"completed"}
 *          ]
 *      }
 *  }
 * }
 *
 *
 * blogId is discovered from current active blog via WordPress Multisite API
 */
class ContentRelationsHandler extends BaseAjaxServiceAbstract
{
    use AjaxSecurityTrait;
    use LoggerSafeTrait;

    public const ACTION_NAME = 'smartling-get-relations';

    public const ACTION_NAME_CREATE_SUBMISSIONS = 'smartling-create-submissions';

    public const FORM_ACTION_UPLOAD = 'upload';

    private ContentRelationsDiscoveryService $service;

    public function __construct(ContentRelationsDiscoveryService $service, private WordpressFunctionProxyHelper $wpProxy)
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
        $authFailure = $this->checkAjaxNonceAndCapability(
            'smartling_translation',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP,
            self::ACTION_NAME_CREATE_SUBMISSIONS,
        );
        if ($authFailure === AjaxAuthorizationFailure::INVALID_NONCE) {
            $this->returnError('invalid.nonce', 'Invalid nonce', 403);
            return;
        }
        if ($authFailure === AjaxAuthorizationFailure::INSUFFICIENT_CAPABILITY) {
            $this->returnError('permission.denied', 'Insufficient permissions', 403);
            return;
        }

        if ($data === null) {
            $data = $_POST;
        }
        try {
            $this->service->createSubmissions(UserTranslationRequest::fromArray($data));
            $this->returnResponse(['status' => BaseAjaxServiceAbstract::RESPONSE_SUCCESS]);
        } catch (Exception $e) {
            $this->returnError('content.submission.failed', $e->getMessage());
        }
    }

    public function actionHandler(): void
    {
        $authFailure = $this->checkAjaxNonceAndCapability(
            'smartling_translation',
            SmartlingUserCapabilities::SMARTLING_CAPABILITY_WIDGET_CAP,
            static::ACTION_NAME,
        );
        if ($authFailure === AjaxAuthorizationFailure::INVALID_NONCE) {
            $this->returnError('invalid.nonce', 'Invalid nonce', 403);
            return;
        }
        if ($authFailure === AjaxAuthorizationFailure::INSUFFICIENT_CAPABILITY) {
            $this->returnError('permission.denied', 'Insufficient permissions', 403);
            return;
        }

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
