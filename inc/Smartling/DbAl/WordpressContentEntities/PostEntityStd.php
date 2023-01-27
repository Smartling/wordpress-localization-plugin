<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\SmartlingDataUpdateException;
use Smartling\Helpers\RawDbQueryHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Services\GlobalSettingsManager;

/**
 * @method setPostContent($string)
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
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PostEntityStd extends EntityAbstract implements EntityWithPostStatus, EntityWithMetadata
{
    /**
     * Standard 'post' content-type fields
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

    public function __construct($type = 'post', array $related = [])
    {
        parent::__construct();
        $this->setType($type);

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

        $this->setRelatedTypes($related);
    }

    /**
     * @return string
     */
    public function getContentTypeProperty()
    {
        return 'post_type';
    }

    public function getId(): ?int
    {
        return $this->ID;
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
    protected function getNonCloneableFields()
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
        if (null !== $post) {
            /**
             * Content loaded from database. Checking if used valid wrapper.
             */
            $entity = $this->resultToEntity($post);
            $entity->validateContentType();

            return $entity;
        }

        $this->entityNotFound($this->getType(), $guid);
    }

    /**
     * @return WordpressFunctionProxyHelper
     */
    protected function getWpProxyHelper()
    {
        return new WordpressFunctionProxyHelper();
    }

    public function getMetadata(): array
    {
        $metadata = $this->getWpProxyHelper()->getPostMeta($this->ID);

        if (!is_array($metadata) || 0 === count($metadata)) {
            $this->rawLogPostMetadata($this->ID);
            return [];
        }

        return $this->formatMetadata($metadata);
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
        // ACF would replace our properly escaped content with its own escaping.
        remove_action('content_save_pre', 'acf_parse_save_blocks', 5);

        /**
         * Content expected to be slashed for
         * @see wp_insert_post() $data declaration
         */
        $addSlashes = GlobalSettingsManager::isAddSlashesBeforeSavingPostContent();
        if ($addSlashes) {
            foreach (['post_author', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_password', 'post_name', 'to_ping', 'pinged'] as $field) {
                if (isset($array[$field])) {
                    $array[$field] = addslashes($array[$field]);
                }
            }
        }
        /** @noinspection JsonEncodingApiUsageInspection failure to json_encode suitable for logging */
        $this->getLogger()->debug(sprintf('Calling wp_insert_post with postArray="%s", addSlashes=%s', json_encode($array), $addSlashes));
        $res = wp_insert_post($array, true);
        if (is_wp_error($res) || 0 === $res) {
            $msg = vsprintf('An error had happened while saving post : \'%s\'', [\json_encode($array)]);
            if (is_wp_error($res)) {
                $msg .= vsprintf(' Error messages: %s', [
                    implode(' | ', $res->get_error_messages()),
                ]);
            }
            $this->getLogger()->error($msg);
            throw new SmartlingDataUpdateException($msg);
        }

        return (int)$res;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $order
     * @param string $searchString
     * @return PostEntityStdWithPostStatus[]
     */
    public function getAll($limit = 0, $offset = 0, $orderBy = 'date', $order = 'DESC', $searchString = '')
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
            's'                => $searchString,
        ];

        $posts = get_posts($arguments);

        $output = [];

        foreach ($posts as $post) {
            // TODO : Remove this dirty workaround
            $item = clone $this;
            $output[] = $item->get($post->ID);
        }

        return $output;
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

    public function getTitle(): string
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
            return $value == $meta[$key];
        } else {
            return false;
        }
    }

    public function setMetaTag($tagName, $tagValue, $unique = true): void
    {
        /**
         * Tag name and value expected to be slashed
         * @see add_metadata()
         */
        $expectedTagName = $tagName;
        $expectedTagValue = $tagValue;
        if (GlobalSettingsManager::isAddSlashesBeforeSavingPostMeta()) {
            if (is_string($tagName)) {
                $tagName = addslashes($tagName);
            }
            if (is_string($tagValue)) {
                $tagValue = addslashes($tagValue);
            }
        }
        if (false === ($result = add_post_meta($this->ID, $tagName, $tagValue, $unique))) {
            $result = update_post_meta($this->ID, $tagName, $tagValue);
        }

        if (false === $result) {
            if (false === $this->ensureMetaValue($expectedTagName, $expectedTagValue)) {
                $message = vsprintf(
                    'Error saving meta tag "%s" with value "%s" for type="%s" id="%s"',
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

    public function translationDrafted(): void
    {
        $this->post_status = 'draft';
    }

    public function translationCompleted(): void
    {
        $this->post_status = 'publish';
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     * @return array
     */
    public function toBulkSubmitScreenRow(): array
    {
        return [
            'id'      => $this->getPK(),
            'title'   => $this->post_title,
            'type'    => $this->post_type,
            'author'  => WordpressUserHelper::getUserLoginById((int)$this->post_author),
            'status'  => $this->post_status,
            'locales' => null,
            'updated' => $this->post_date,
        ];
    }
}
