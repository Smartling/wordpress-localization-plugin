<?php

namespace Smartling;

/**
 * Interface SmartlingTableDefinitionInterface
 *
 * @package Smartling
 */
interface SmartlingTableDefinitionInterface
{
    /**
     * @return array
     */
    static function getFieldLabels();

    /**
     * @return array
     */
    static function getFieldDefinitions();

    /**
     * @return array
     */
    static function getSortableFields();

    /**
     * @return array
     */
    static function getIndexes();

    /**
     * @return string
     */
    static function getTableName();

    /**
     * @param $fieldName
     *
     * @return string
     */
    static function getFieldLabel($fieldName);
}