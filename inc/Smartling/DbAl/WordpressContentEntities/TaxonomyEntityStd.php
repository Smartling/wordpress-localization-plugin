<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;

/**
 * @property int term_id
 * @property string name
 * @property string slug
 * @property int term_group
 * @property int term_taxonomy_id
 * @property string taxonomy
 * @property string description
 * @property int parent
 * @property int count
 */
class TaxonomyEntityStd extends EntityAbstract
{
    /**
     * Standard taxonomy fields
     * @var array
     */
    protected $fields = [
        'term_id',
        'name',
        'slug',
        'term_group',
        'term_taxonomy_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    private WordpressFunctionProxyHelper $wordpressProxy;

    public function getMetadata(): array
    {
        $metadata = $this->wordpressProxy->get_term_meta($this->getPK());

        if (!is_array($metadata) || 0 === count($metadata)) {
            $this->getLogger()->warning('Expected to get metadata array for termId=' . $this->getPK() . ', got ' . (is_array($metadata) ? 'empty array' : gettype($metadata)));
            return [];
        }

        return $this->formatMetadata($metadata);
    }

    public function setMetaTag($tagName, $tagValue, $unique = true): void
    {
        $curValue = get_term_meta($this->getPK(), $tagName, $unique);

        $result = null;

        if ($curValue !== $tagValue) {
            if (false === $curValue) {
                $this->logMessage(vsprintf('Adding tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                    $tagName,
                    var_export($tagValue, true),
                    $this->type,
                    $this->getPK(),
                ]));
                $result = add_term_meta($this->getPK(), $tagName, $tagValue, $unique);
            } else {
                $this->logMessage(vsprintf('Updating tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                    $tagName,
                    var_export($tagValue, true),
                    $this->type,
                    $this->getPK(),
                ]));
                $result = update_term_meta($this->getPK(), $tagName, $tagValue);
            }
        } else {
            $this->logMessage(vsprintf('Skipping update tag %s with value \'%s\' for \'%s\' \'%s\' as value not changed.', [
                $tagName,
                var_export($tagValue, true),
                $this->type,
                $this->getPK(),
            ]));
        }

        if (false === $result) {
            $message = vsprintf(
                'Error saving meta tag "%s" with value "%s" for "%s" "%s"',
                [
                    $tagName,
                    var_export($tagValue, true),
                    $this->type,
                    $this->getPK(),
                ]
            );

            $this->getLogger()
                ->error($message);
        }

    }

    public function __construct(string $type, array $related = [], WordpressFunctionProxyHelper $wordpressProxy = null)
    {
        parent::__construct();

        $this->hashAffectingFields = [
            'name',
            'description',
        ];

        $this->setEntityFields($this->fields);
        $this->setType($type);
        $this->setRelatedTypes($related);
        if ($wordpressProxy === null) {
            $wordpressProxy = new WordpressFunctionProxyHelper();
        }
        $this->wordpressProxy = $wordpressProxy;
    }

    /**
     * @return string
     */
    public function getContentTypeProperty()
    {
        return 'taxonomy';
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->name;
    }

    /**
     * @param $guid
     *
     * @return TaxonomyEntityStd
     * @throws SmartlingDbException
     * @throws EntityNotFoundException
     */
    public function get($guid)
    {
        $term = get_term($guid, $this->getType(), ARRAY_A);
        $entity = null;

        if ($term instanceof \WP_Error) {
            $message = vsprintf(
                'An error occurred while reading taxonomy id=%s, type=%s: %s',
                [$guid, $this->getType(), $term->get_error_message()]);

            $this->getLogger()->error($message);

            throw new SmartlingDbException($message);
        }

        if (null === $term) {
            $this->entityNotFound($this->getType(), $guid);
        }

        $entity = $this->resultToEntity($term);

        if (false === $entity->validateContentType()) {
            $this->entityNotFound($this->getType(), $guid);
        }

        return $entity;
    }

    /**
     * Loads ALL entities from database
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $order
     * @param string $searchString
     * @return TaxonomyEntityStd[]
     * @throws SmartlingDbException
     */
    public function getAll($limit = 0, $offset = 0, $orderBy = 'term_id', $order = 'ASC', $searchString = '')
    {

        $result = [];

        $taxonomies = [
            $this->getType(),
        ];

        $args = [
            'orderby'           => $orderBy,
            'order'             => $order,
            'hide_empty'        => false,
            'exclude'           => [],
            'exclude_tree'      => [],
            'include'           => [],
            'number'            => $limit,
            'fields'            => 'all',
            'slug'              => '',
            'parent'            => '',
            'hierarchical'      => true,
            'child_of'          => 0,
            'get'               => '',
            'name__like'        => '',
            'description__like' => '',
            'pad_counts'        => false,
            'offset'            => $offset,
            'search'            => $searchString,
            'cache_domain'      => 'core',
        ];

        $terms = get_terms($taxonomies, $args);

        if ($terms instanceof \WP_Error) {
            $message = vsprintf('An error occurred while reading all taxonomies of type %s: %s',
                                [$this->getType(), $terms->get_error_message()]);

            $this->getLogger()
                ->error($message);

            throw new SmartlingDbException($message);
        }

        foreach ($terms as $term) {
            $result[] = $this->resultToEntity((array)$term);
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return wp_count_terms($this->getType());
    }

    /**
     * @param EntityAbstract $entity
     *
     * @return array
     * @throws SmartlingDbException
     */
    public function set(EntityAbstract $entity = null)
    {

        $me = get_class($this);

        if (!($entity instanceof $me)) {
            $entity = $this;
        }

        $update = !(null === $entity->term_id);

        $data = $entity->toArray();

        $argFields = [
            'name',
            //'slug',
            'parent',
            'description',
        ];

        $args = [];


        foreach ($argFields as $field) {
            $args[$field] = $data[$field];
        }

        $result = $update
            ? wp_update_term($entity->term_id, $entity->taxonomy, $args)
            : wp_insert_term($entity->name, $entity->taxonomy, $args);

        if ($result instanceof \WP_Error) {
            if (isset($result->error_data) && array_key_exists('term_exists', $result->error_data)) {
                $entity->term_id = (int)$result->error_data['term_exists'];

                return $this->set($entity);

            }

            $message = vsprintf(
                'An error occurred while saving taxonomy id=%s, type=%s: %s',
                [
                    ($entity->term_id ?: '<none>'),
                    $this->getType(),
                    $result->get_error_message(),
                ]
            );

            $this->getLogger()
                ->error($message);

            throw new SmartlingDbException($message);
        }

        foreach ($result as $field => $value) {
            $entity->$field = $value;
        }

        return $entity->{$this->getPrimaryFieldName()};

    }

    /**
     * @inheritdoc
     */
    protected function getNonCloneableFields()
    {
        return [
            'term_id',
            'parent',
            'count',
            'slug',
        ];
    }

    public static function detectTermTaxonomyByTermId($termId)
    {
        $taxonomies = WordpressContentTypeHelper::getSupportedTaxonomyTypes();

        $args = [
            'orderby'           => 'term_id',
            'order'             => 'ASC',
            'hide_empty'        => false,
            'exclude'           => [],
            'exclude_tree'      => [],
            'include'           => [],
            'number'            => '',
            'fields'            => 'all',
            'slug'              => '',
            'parent'            => '',
            'hierarchical'      => true,
            'child_of'          => 0,
            'get'               => '',
            'name__like'        => '',
            'description__like' => '',
            'pad_counts'        => false,
            'offset'            => '',
            'search'            => '',
            'cache_domain'      => 'core',
        ];

        $terms = get_terms($taxonomies, $args);


        $result = [];

        if ($terms instanceof \WP_Error) {
            $message = vsprintf('An error occurred while readin all taxonomies of type: %s',
                                [$terms->get_error_message()]);

            throw new SmartlingDbException($message);
        }

        foreach ($terms as $term) {
            if ((int)$term->term_id === (int)$termId) {
                $result[] = $term->taxonomy;
                break;
            }
        }

        return $result;
    }

    public function cleanFields($value = null)
    {
        parent::cleanFields($value);
        $this->term_taxonomy_id = $value;
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryFieldName()
    {
        return 'term_id';
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     * @return array
     */
    public function toBulkSubmitScreenRow()
    {
        return [
            'id'      => $this->term_id,
            'title'   => $this->name,
            'type'    => $this->taxonomy,
            'author'  => null,
            'status'  => null,
            'locales' => null,
            'updated' => null,
        ];
    }
}