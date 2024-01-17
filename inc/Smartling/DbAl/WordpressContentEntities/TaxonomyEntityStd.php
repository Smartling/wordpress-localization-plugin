<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\WP\View\BulkSubmitScreenRow;

/**
 * @property int    $term_id
 * @property string $name
 * @property string slug
 * @property int    $term_group
 * @property int    $term_taxonomy_id
 * @property string $taxonomy
 * @property string $description
 * @property int    $parent
 * @property int    $count
 */
class TaxonomyEntityStd extends EntityAbstract implements EntityWithMetadata
{
    /**
     * Standard taxonomy fields
     */
    protected array $fields = [
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

    public function setMetaTag(string $key, $value, $unique = true): void
    {
        $curValue = get_term_meta($this->getPK(), $key, $unique);

        $result = null;

        if ($curValue !== $value) {
            if (false === $curValue) {
                $this->logMessage(vsprintf('Adding tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                    $key,
                    var_export($value, true),
                    $this->type,
                    $this->getPK(),
                ]));
                $result = add_term_meta($this->getPK(), $key, $value, $unique);
            } else {
                $this->logMessage(vsprintf('Updating tag %s with value \'%s\' for \'%s\' \'%s\'.', [
                    $key,
                    var_export($value, true),
                    $this->type,
                    $this->getPK(),
                ]));
                $result = update_term_meta($this->getPK(), $key, $value);
            }
        } else {
            $this->logMessage(vsprintf('Skipping update tag %s with value \'%s\' for \'%s\' \'%s\' as value not changed.', [
                $key,
                var_export($value, true),
                $this->type,
                $this->getPK(),
            ]));
        }

        if (false === $result) {
            $message = vsprintf(
                'Error saving meta tag "%s" with value "%s" for "%s" "%s"',
                [
                    $key,
                    var_export($value, true),
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

    public function getContentTypeProperty(): string
    {
        return 'taxonomy';
    }

    public function getId(): ?int
    {
        return $this->term_id;
    }

    public function getTitle(): string
    {
        return $this->name;
    }

    /**
     * @throws SmartlingDbException
     * @throws EntityNotFoundException
     */
    public function get(mixed $id): self
    {
        $term = get_term($id, $this->getType(), ARRAY_A);

        if ($term instanceof \WP_Error) {
            $message = vsprintf(
                'An error occurred while reading taxonomy id=%s, type=%s: %s',
                [$id, $this->getType(), $term->get_error_message()]);

            $this->getLogger()->error($message);

            throw new SmartlingDbException($message);
        }

        if (null === $term) {
            $this->entityNotFound($this->getType(), $id);
        }

        $entity = $this->resultToEntity($term);

        if (false === $entity->validateContentType()) {
            $this->entityNotFound($this->getType(), $id);
        }

        return $entity;
    }

    /**
     * Loads ALL entities from database
     * @return TaxonomyEntityStd[]
     * @throws SmartlingDbException
     */
    public function getAll(
        int $limit = 0,
        int $offset = 0,
        string $orderBy = 'term_id',
        string $order = 'ASC',
        string $searchString = '',
        array $includeOnlyIds = [],
    ): array {
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
            'include'           => $includeOnlyIds,
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
    public function getTotal(): int
    {
        return wp_count_terms($this->getType());
    }

    /**
     * @throws SmartlingDbException
     */
    public function set(Entity $entity): int
    {
        if (!$entity instanceof self) {
            throw new \InvalidArgumentException(__CLASS__ . ' can only set itself, ' . get_class($entity) . ' provided');
        }
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

        $result = null !== $entity->term_id
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

    protected function getNonCloneableFields(): array
    {
        return [
            'term_id',
            'parent',
            'count',
            'slug',
        ];
    }

    public function forInsert(): static
    {
        $result = parent::forInsert();
        $result->term_taxonomy_id = null;

        return $result;
    }

    public function getPrimaryFieldName(): string
    {
        return 'term_id';
    }

    /**
     * Converts instance of EntityAbstract to array to be used for BulkSubmit screen
     */
    public function toBulkSubmitScreenRow(): BulkSubmitScreenRow
    {
        return new BulkSubmitScreenRow($this->term_id, $this->name, $this->taxonomy);
    }
}