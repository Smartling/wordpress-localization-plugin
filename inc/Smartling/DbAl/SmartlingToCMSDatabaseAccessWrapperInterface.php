<?php

namespace Smartling\DbAl;

interface SmartlingToCMSDatabaseAccessWrapperInterface
{
    public const SORT_OPTION_ASC = 'ASC';
    public const SORT_OPTION_DESC = 'DESC';

    /**
     * @return bool|int
     */
    public function query(string $query);

    /**
     * @return bool|int
     */
    public function queryPrepared(string $query, ...$args);

    /**
     * @param string $output \OBJECT || \ARRAY_A
     * @return array|null|object
     */
    public function fetch(string $query, string $output = OBJECT);

    public function fetchPrepared(string $query, ...$args): array;

    public function escape(string $string): string;

    public function completeTableName(string $tableName): string;

    public function completeMultisiteTableName(string $tableName): string;

    public function getLastInsertedId(): int;

    public function getLastErrorMessage(): string;

    public function getPrefix(): string;
}
