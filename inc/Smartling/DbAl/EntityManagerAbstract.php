<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;

abstract class EntityManagerAbstract {

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SmartlingToCMSDatabaseAccessWrapper
     */
    protected $dbal;

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param SmartlingToCMSDatabaseAccessWrapper $dbal
     */
    public function __construct(LoggerInterface $logger, SmartlingToCMSDatabaseAccessWrapper $dbal)
    {
        $this->logger = $logger;
        $this->dbal = $dbal;
    }

    /**
     * Validates sorting options
     * @param null|array $sortOptions
     * @param array $fieldNames
     * @return bool true or false
     */
    protected function validateSortOptions($sortOptions, array $fieldNames)
    {
        $valid = null;

        switch (true) {

            // no sorting enabled
            case is_null($sortOptions) : {
                $valid = true;
                break;
            }

            // some sorting enabled
            case is_array($sortOptions) : {

                $fielsValues = array(
                    SmartlingToCMSDatabaseAccessWrapper::SORT_OPTION_ASC,
                    SmartlingToCMSDatabaseAccessWrapper::SORT_OPTION_DESC
                );



                // array cannot be empty
                $valid = (!empty($sortOptions));

                foreach ($sortOptions as $field => $order) {
                    if (!in_array($field, $fieldNames) || !in_array($order, $fielsValues)) {
                        $valid = false;
                        break;
                    }
                }

                break;
            }

            // not null or array
            default : {
                $valid = false;
                break;
            }
        }
        return $valid;
    }

    /**
     * @param $pageOptions
     * @return bool true or false
     */
    protected function validatePageOptions($pageOptions)
    {
        $valid = null;

        switch (true) {

            // no sorting enabled
            case is_null($pageOptions) : {
                $valid = true;
                break;
            }

            // some sorting enabled
            case is_array($pageOptions) : {

                //array('limit' => 20, 'page' => 1)

                $validLimit = isset($pageOptions['limit']) && 0 < (int) $pageOptions['limit'];

                $validPage = isset($pageOptions['page']) && 0 < (int) $pageOptions['page'];

                $valid = $validLimit && $validPage;

                break;
            }

            // not null or array
            default : {
                $valid = false;
                break;
            }
        }
        return $valid;
    }

    /**
     * Builds SQL query to select entities
     * @param $tableName
     * @param $fieldsList
     * @param $whereFields
     * @param $sortOptions
     * @param $pageOptions
     * @return string
     */
    protected function buildSelectQuery($tableName, $fieldsList, $whereFields, $sortOptions, $pageOptions)
    {
        $query = vsprintf(
            "SELECT %s FROM `%s`",
            array(
                '`' . implode('`, `', $fieldsList) . '`',
                $this->dbal->completeTableName($tableName)
            )
        );

        if (!empty($whereFields)){
            $preOptions = array();

            foreach($whereFields as $filed => $value){

                $sign = '=';

                $val = $value;

                if(is_array($value)){
                    $sign = $value['operator'];
                    $val = $value['value'];
                }

                $preOptions[] = vsprintf("`%s` %s '%s'", array($filed, $sign, $val));
            }

            $query .= vsprintf(" WHERE %s", array(implode(' AND ', $preOptions)));

        }

        $query .= $this->buildSortSubQuery($sortOptions);

        $query .= $this->buildLimitSubQuery($pageOptions);

        return $query;
    }

    private function buildSortSubQuery($sortOptions)
    {
        $part = '';

        if(!is_null($sortOptions)){
            $preOptions = array();

            foreach($sortOptions as $filed => $value){
                $preOptions[] = vsprintf("`%s` %s", array($filed, $value));
            }

            $part .= vsprintf(" ORDER BY %s", array(implode(' , ', $preOptions)));
        }

        return $part;
    }

    private function buildLimitSubQuery($pageOptions)
    {
        $part = '';

        if (!is_null($pageOptions)) {

            $limit = (int) $pageOptions['limit'];

            $offset = (((int) $pageOptions['page']) - 1) * $limit;

            $part .= vsprintf(' LIMIT %d,%d', array($limit, $offset));
        }

        return $part;
    }

}