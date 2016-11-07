<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDataUpdateException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\RawDbQueryHelper;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PostEntity
 *
 * @property null|integer $ID
 * @property integer      $post_author
 * @property string       $post_date
 * @property string       $post_date_gmt
 * @property string       $post_content
 * @property string       $post_title
 * @property null|integer $post_excerpt
 * @property string       $post_status
 * @property null|integer $comment_status
 * @property string       $ping_status
 * @property string       $post_password
 * @property string       $post_name
 * @property string       $to_ping
 * @property string       $pinged
 * @property string       $post_modified
 * @property string       $post_modified_gmt
 * @property string       $post_content_filtered
 * @property integer      $post_parent
 * @property string       $guid
 * @property integer      $menu_order
 * @property string       $post_type
 * @property string       $post_mime_type
 * @property integer      $comment_count
 * @property string       $hash
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PostEntity extends EntityAbstract
{

    /**
     * Standard 'post' content-type fields
     *
     * @var array
     */
    protected $fields = [
        'ID',
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'to_ping',
        'pinged',
        'post_modified',
        'post_modified_gmt',
        'post_content_filtered',
        'post_parent',
        'guid',
        'menu_order',
        'post_type',
        'post_mime_type',
        'comment_count',
    ];

    /**
     * @inheritdoc
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);

        $this->setType(WordpressContentTypeHelper::CONTENT_TYPE_POST);
        $this->hashAffectingFields = array_merge($this->hashAffectingFields, [
            'ID',
            'post_author',
            'post_content',
            'post_title',
            'post_status',
            'comment_status',
            'post_name',
            'post_parent',
            'guid',
            'post_type',
        ]);


        $this->setEntityFields($this->fields);

        $this->setRelatedTypes([
            WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY,
            WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG,
        ]);
    }

    /**
     * @return string
     */
    public function getContentTypeProperty()
    {
        return 'post_type';
    }

    /**
     * @inheritdoc
     */
    protected function getFieldNameByMethodName($method)
    {
        $fieldName = parent::getFieldNameByMethodName($method);

        // wordpress uses ID instead of id
        if ('iD' === $fieldName) {
            $fieldName = 'ID';
        }

        return $fieldName;
    }

    /**
     * @inheritdoc
     */
    protected function getNonClonableFields()
    {
        return [
            'comment_count',
            'guid',
            'ID',
        ];
    }

    /**
     * @inheritdoc
     */
    public function get($guid)
    {
        $post = get_post($guid, ARRAY_A);
        $entity = null;
        if (null !== $post) {
            /**
             * Content loaded from database. Checking if used valid wrapper.
             */
            $entity = $this->resultToEntity($post, $this);
            if (false === $entity->validateContentType()) {
                $this->entityNotFound(WordpressContentTypeHelper::CONTENT_TYPE_POST, $guid);
            }
            return $entity;
        } else {
            $this->entityNotFound(WordpressContentTypeHelper::CONTENT_TYPE_POST, $guid);
        }
    }

    /**
     * @inheritdoc
     */
    public function getMetadata()
    {
        $metadata = get_post_meta($this->ID);

        if ((is_array($metadata) && 0 === count($metadata)) || !is_array($metadata)) {
            $this->rawLogPostMetadata($this->ID);
        }

        return $metadata;
    }

    private function rawLogPostMetadata($postId)
    {
        $query = vsprintf(
            'SELECT * FROM %s WHERE post_id=%d',
            [
                RawDbQueryHelper::getTableName('postmeta'),
                $postId,
            ]
        );

        $data = RawDbQueryHelper::query($query);

        // $data may be array|null|Object

        if (is_null($data)) {
            $message = vsprintf(
                'get_post_meta(%d) (Query %s) returned empty array. Raw result is NULL.',
                [
                    $postId,
                    var_export($query, true),
                ]
            );
        } elseif (is_array($data)) {
            $message = vsprintf(
                'get_post_meta(%d) returned empty array. Raw result is:%s',
                [
                    $postId,
                    base64_encode( // safe saving
                        json_encode( // easy reading
                            $data,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        )
                    ),
                ]
            );
        } elseif (is_object($data)) {
            $message = vsprintf(
                'get_post_meta(%d) returned empty array. Raw result is:%s',
                [
                    $postId,
                    base64_encode( // safe saving
                        var_export($data, true)
                    ),
                ]
            );
        } else {
            $message = vsprintf(
                'get_post_meta(%d) returned empty array. Raw result is: type:%s, value:%s',
                [
                    $postId,
                    gettype($data),
                    base64_encode( // safe saving
                        var_export($data, true)
                    ),
                ]
            );
        }

        $this->logMessage($message);
    }

    /**
     * @inheritdoc
     */
    public function set(EntityAbstract $entity = null)
    {
        $instance = null === $entity ? $this : $entity;

        $array = $instance->toArray();

        $array['post_category'] = \wp_get_post_categories($instance->ID);

        $res = wp_insert_post($array, true);

        if (is_wp_error($res)) {

            $msgFields = [];

            $curFields = $entity->toArray();

            foreach ($curFields as $field => $value) {
                $msgFields[] = vsprintf("%s = %s", [$field, htmlentities($value)]);
            }

            $message = vsprintf('An error had happened while saving post to database: %s. Params: %s',
                [implode(' | ', $res->get_error_messages()), implode(' || ', $msgFields)]);

            $this->getLogger()
                 ->error($message);

            throw new SmartlingDataUpdateException($message);

        }

        return (int)$res;
    }


    /**
     * @param string $limit
     * @param int    $offset
     * @param string $orderBy
     * @param string $order
     *
     * @return array
     */
    public function getAll($limit = '', $offset = 0, $orderBy = 'date', $order = 'DESC')
    {

        $arguments = [
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'category'         => 0,
            'orderby'          => $orderBy,
            'order'            => $order,
            'include'          => [],
            'exclude'          => [],
            'meta_key'         => '',
            'meta_value'       => '',
            'post_type'        => $this->getType(),
            'suppress_filters' => true,
        ];

        $posts = get_posts($arguments);

        return $posts;

    }

    /**
     * @return int
     */
    public function getTotal()
    {
        $wp = wp_count_posts($this->getType());

        $wp = (array)$wp;

        $total = 0;

        $cnt = function ($name) use ($wp) {
            return array_key_exists($name, $wp) ? (int)$wp[$name] : 0;
        };

        $fields = [
            'publish',
            'future',
            'draft',
            'pending',
            'private',
            'autoDraft',
            'inherit',
        ];

        foreach ($fields as $field) {
            $total += $cnt($field);
        }

        return $total;
    }

    public function getTitle()
    {
        return $this->post_title;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryFieldName()
    {
        return 'ID';
    }

    /**
     * returns true if value is same as stored.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    private function ensureMetaValue($key, $value)
    {
        $meta = $this->getMetadata();

        if (is_array($meta) && array_key_exists($key, $meta)) {
            return $value == ArrayHelper::first($meta[$key]);
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function setMetaTag($tagName, $tagValue, $unique = true)
    {
        $result = null;
        if (metadata_exists(WordpressContentTypeHelper::CONTENT_TYPE_POST, $this->ID, $tagName)) {
            $this->logMessage(vsprintf('Updating tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                $tagName,
                var_export($tagValue, true),
                $this->post_type,
                $this->ID,
            ]));
            $result = update_post_meta($this->ID, $tagName, $tagValue);
        } else {
            $this->logMessage(vsprintf('Adding tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                $tagName,
                var_export($tagValue, true),
                $this->post_type,
                $this->ID,
            ]));
            $this->getLogger()
                 ->info('adding tag ' . $tagName);
            $result = add_post_meta($this->ID, $tagName, $tagValue, $unique);
        }

        if (false === $result) {
            if (false === $this->ensureMetaValue($tagName, $tagValue)) {
                $message = vsprintf(
                    'Error saving meta tag "%s" with value "%s" for "%s" "%s"',
                    [
                        $tagName,
                        var_export($tagValue, true),
                        $this->post_type,
                        $this->ID,
                    ]
                );
                $this->getLogger()
                     ->error($message);
            }
        } else {
            $this->logMessage(
                vsprintf('Set tag \'%s\' with value \'%s\' for %s (id=%s)', [
                    $tagName,
                    var_export($tagValue, true),
                    $this->post_type,
                    $this->ID,
                ]));
        }
    }

    public function translationDrafted()
    {
        $this->setPostStatus('draft');
    }

    public function translationCompleted()
    {
        $this->setPostStatus('publish');
    }
}