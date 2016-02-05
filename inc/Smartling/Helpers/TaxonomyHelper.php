<?php

namespace Smartling\Helpers;

use InvalidArgumentException;
use Smartling\Exception\SmartlingDbException;

/**
 * Class TaxonomyHelper
 * @package Smartling\Helpers
 */
class TaxonomyHelper
{
    /**
     * @return \wpdb
     */
    private static function getWpdb()
    {
        global $wpdb;

        return $wpdb;
    }

    /**
     * @param string $taxonomyType
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function checkTaxonomyType($taxonomyType)
    {
        $taxonomyExists = taxonomy_exists($taxonomyType);

        if (false === $taxonomyExists) {
            $message = vsprintf('Invalid taxonomy: %s', [$taxonomyType]);
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @param int|string $term // Smartling-connector sends only int values
     * @param string     $taxonomy
     *
     * @return array
     * @throws SmartlingDbException
     * @throws \InvalidArgumentException
     */
    private static function getTermInfo($term, $taxonomy)
    {
        $termInfo = term_exists($term, $taxonomy);

        if (!$termInfo) {
            if (is_int($term)) {
                $message = vsprintf('Cannot get info of term id=%s', [$term]);
                throw new InvalidArgumentException($message);
            }

            $termInfo = wp_insert_term($term, $taxonomy);
        }

        if (is_wp_error($termInfo)) {
            $message = vsprintf('Error while inserting new term: ', [implode('|', $termInfo->get_error_messages())]);
            throw new SmartlingDbException($message);
        }

        return $termInfo;
    }

    /**
     * @param int $objectId
     * @param int $termTaxonomyId
     *
     * @return bool
     * @throws SmartlingDbException
     */
    private static function checkTermTaxonomyRelation($objectId, $termTaxonomyId)
    {
        $query = vsprintf(
            'SELECT term_taxonomy_id FROM %s WHERE object_id = %d AND term_taxonomy_id = %d',
            [
                self::getWpdb()->term_relationships,
                $objectId,
                $termTaxonomyId,
            ]
        );

        $result = self::getWpdb()
                      ->get_results($query, ARRAY_A);

        if (!is_array($result)) {
            $message = vsprintf(
                'Invalid value returned by $wpdb->get_results with query \'%s\', expected array, got: %s',
                [
                    $query,
                    var_export($result, true),
                ]
            );

            throw new SmartlingDbException($message);
        }

        return count($result) > 0;
    }

    private static function addTermTaxonomyRelation($objectId, $termTaxonomyId)
    {
        return self::getWpdb()
                   ->insert(
                       self::getWpdb()->term_relationships,
                       [
                           'object_id'        => $objectId,
                           'term_taxonomy_id' => $termTaxonomyId,
                       ]
                   );
    }

    /**
     * @param array  $originalTerms
     * @param array  $newTermIds
     * @param int    $objectId
     * @param string $taxonomy
     *
     * @throws SmartlingDbException
     */
    private static function deleteOldRelations($originalTerms, $newTermIds, $objectId, $taxonomy)
    {
        $deleteList = array_diff($originalTerms, $newTermIds);

        if (0 < count($deleteList)) {
            $query = vsprintf('SELECT tt.term_id FROM %s AS tt WHERE tt.taxonomy = \'%s\' AND tt.term_taxonomy_id IN (%s)',
                [
                    self::getWpdb()->term_taxonomy,
                    $taxonomy,
                    vsprintf('\'%s\'', [implode('\',\'', $deleteList)]),
                ]
            );

            $col = self::getWpdb()
                       ->get_col($query);

            $ids = array_map('intval', $col);

            $result = wp_remove_object_terms($objectId, $ids, $taxonomy);

            if (is_wp_error($result)) {
                $message = vsprintf(
                    'DB Error occurred while removing object terms (objectId=\'%s\', termIds={\'%s\'}, taxonomy=\'%s\'). Message: %s.',
                    [
                        $objectId,
                        implode(',', $ids),
                        $taxonomy,
                        implode('|', $result->get_error_messages()),

                    ]
                );

                throw new SmartlingDbException($message);
            }


        }
    }

    /**
     * @param string $taxonomyType
     * @param int    $objectId
     * @param array  $newTermIds
     *
     * @throws SmartlingDbException
     */
    private static function sortObjectTerms($taxonomyType, $objectId, $newTermIds)
    {
        $t = get_taxonomy($taxonomyType);

        if (isset($t->sort) && $t->sort) {
            $values = [];
            $term_order = 0;

            $final_tt_ids = wp_get_object_terms($objectId, $taxonomyType, ['fields' => 'tt_ids']);

            foreach ($newTermIds as $termTaxonomyId) {
                if (in_array($termTaxonomyId, $final_tt_ids)) {
                    $values[] = self::getWpdb()
                                    ->prepare('(%d, %d, %d)', $objectId, $termTaxonomyId, ++$term_order);
                }
            }

            if (0 < count($values)) {
                $query = vsprintf(
                    'INSERT INTO %s (object_id, term_taxonomy_id, term_order) VALUES %s ON DUPLICATE KEY UPDATE term_order = VALUES(term_order)',
                    [
                        self::getWpdb()->term_relationships,
                        implode(',', $values),
                    ]
                );

                $queryResult = self::getWpdb()
                                   ->query($query);

                if (false === $queryResult) {
                    $message = vsprintf(
                        'Could not insert term relationship into the database: %s',
                        [
                            self::getWpdb()->last_error,
                        ]
                    );

                    throw new SmartlingDbException($message);
                }
            }
        }
    }

    /**
     * @param int    $objectId
     * @param array  $terms
     * @param string $taxonomyType
     *
     * @return array
     * @throws \Exception
     */
    public static function setObjectTerms($objectId, array $terms, $taxonomyType)
    {
        try {
            self::checkTaxonomyType($taxonomyType);
            $currentObjectTaxonomyTerms = wp_get_object_terms($objectId, $taxonomyType,
                [
                    'fields'  => 'tt_ids',
                    'orderby' => 'none',
                ]
            );
            $newTaxonomyTerms = [];
            foreach ($terms as $term) {
                $termInfo = self::getTermInfo($term, $taxonomyType);
                $termTaxonomyId = $termInfo['term_taxonomy_id'];
                $relationExists = self::checkTermTaxonomyRelation($objectId, $termTaxonomyId);
                if (false === $relationExists) {
                    $result = self::addTermTaxonomyRelation($objectId, $termTaxonomyId);
                }
                $newTaxonomyTerms[] = $termTaxonomyId;
            }

            if ($newTaxonomyTerms) {
                wp_update_term_count($newTaxonomyTerms, $taxonomyType);
            }

            self::deleteOldRelations($currentObjectTaxonomyTerms, $newTaxonomyTerms, $objectId, $taxonomyType);
            self::sortObjectTerms($taxonomyType, $objectId, $newTaxonomyTerms);

            return $newTaxonomyTerms;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}