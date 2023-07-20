<?php

namespace Smartling\Settings;

use Smartling\DbAl\EntityManagerAbstract;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\TestRunHelper;
use Smartling\Submissions\SubmissionEntity;

class SettingsManager extends EntityManagerAbstract
{
    /**
     * @return ConfigurationProfileEntity[]
     */
    public function getEntities(int &$totalCount = 0, bool $onlyActive = false): array
    {
        $cb = null;
        if ($onlyActive) {
            $cb = ConditionBlock::getConditionBlock();
            $cb->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'is_active', [1,]));
        }
        $dataQuery = $this->buildQuery($cb);
        $countQuery = $this->buildCountQuery();
        $tc = $this->getDbal()->fetch($countQuery);
        if (1 === count($tc)) {
            // extracting from result
            $totalCount = (int)$tc[0]->cnt;
        }

        return $this->fetchData($dataQuery);
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    public function getActiveProfiles(): array
    {
        $cnt = 0;

        return $this->getEntities($cnt, true);
    }

    public function getSmartlingLocaleBySubmission(SubmissionEntity $submission): string
    {
        $profile = $this->getSingleSettingsProfile($submission->getSourceBlogId());
        if (TestRunHelper::isTestRunBlog($submission->getTargetBlogId())) {
            if (count($profile->getTargetLocales()) === 0) {
                throw new SmartlingConfigException('Profile ' . $profile->getProfileName() . ' (' . $profile->getProjectId() . ') is expected to have at least one target locale for test run');
            }
            return ArrayHelper::first($profile->getTargetLocales())->getSmartlingLocale();
        }

        return $this->getSmartlingLocaleIdBySettingsProfile($profile, $submission->getTargetBlogId());
    }

    /**
     * @throws SmartlingDbException
     */
    public function getSingleSettingsProfile(int $mainBlogId): ConfigurationProfileEntity
    {
        $possibleProfiles = $this->findEntityByMainLocale($mainBlogId);

        if (0 < count($possibleProfiles)) {
            return ArrayHelper::first($possibleProfiles);
        }

        $message = vsprintf('No active profile found for main blog %s', [$mainBlogId]);
        $this->getLogger()->warning($message);
        throw new SmartlingDbException($message);
    }

    /**
     * @return int[]
     * @throws SmartlingDbException
     * @throws SmartlingConfigException
     */
    public function getProfileTargetBlogIdsByMainBlogId(int $mainBlogId): array
    {
        $profile = $this->getSingleSettingsProfile($mainBlogId);

        $targetBlogIds = [];

        foreach ($profile->getTargetLocales() as $targetLocale) {
            if ($targetLocale->isEnabled()) {
                $targetBlogIds[]=$targetLocale->getBlogId();
            }
        }

        if (0 < count($targetBlogIds)) {
            return $targetBlogIds;
        }

        throw new SmartlingConfigException(vsprintf('No active target locales found for profile id=%s.', [$profile->getId()]));
    }

    public function getSmartlingLocaleIdBySettingsProfile(ConfigurationProfileEntity $profile, int $targetBlog): string
    {
        $locale = '';

        $locales = $profile->getTargetLocales();
        foreach ($locales as $item) {
            if ($targetBlog === $item->getBlogId()) {
                $locale = $item->getSmartlingLocale();
                break;
            }
        }

        return $locale;
    }

    /**
     * @throws SmartlingConfigException
     */
    public function getActiveProfileByProjectId(string $projectId): ConfigurationProfileEntity
    {
        $cond = ConditionBlock::getConditionBlock();
        $cond->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'project_id', [$projectId]));
        $cond->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'is_active', [1]));
        $dataQuery = $this->buildQuery($cond);
        $result = $this->fetchData($dataQuery);

        if (0 < count($result)) {
            return ArrayHelper::first($result);
        }

        throw new SmartlingConfigException(vsprintf('No profile found for projectId="%s".', [$projectId]));
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    public function getEntityById(int $id): array
    {
        $cond = ConditionBlock::getConditionBlock();
        $cond->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [$id]));
        $dataQuery = $this->buildQuery($cond);

        return $this->fetchData($dataQuery);
    }

    protected function dbResultToEntity(array $dbRow): ConfigurationProfileEntity
    {
        return ConfigurationProfileEntity::fromArray($dbRow, $this->getLogger());
    }

    private function buildQuery(ConditionBlock $whereOptions = null): string
    {
        $query = QueryBuilder::buildSelectQuery($this->getDbal()
                                                    ->completeTableName(ConfigurationProfileEntity::getTableName()),
                                                array_keys(ConfigurationProfileEntity::getFieldDefinitions()),
                                                $whereOptions);
        $this->logQuery($query);

        return $query;
    }

    public function buildCountQuery(): string
    {
        $query = QueryBuilder::buildSelectQuery(
            $this->getDbal()->completeTableName(ConfigurationProfileEntity::getTableName()),
            [['COUNT(*)' => 'cnt']],
        );
        $this->logQuery($query);

        return $query;
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    public function fetchData($query): array
    {
        $data = parent::fetchData($query);
        foreach ($data as $result) {
            if (!$result instanceof ConfigurationProfileEntity) {
                throw new \RuntimeException(ConfigurationProfileEntity::class . ' expected');
            }
            $this->updateLabels($result);
        }

        return $data;
    }

    /**
     * @return ConfigurationProfileEntity[]
     */
    public function findEntityByMainLocale(int $sourceBlogId): array
    {
        $conditionBlock = ConditionBlock::getConditionBlock(ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND);
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'original_blog_id',
            [$sourceBlogId]));
        $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'is_active', [1]));

        return $this->fetchData($this->buildQuery($conditionBlock));
    }

    public function storeEntity(ConfigurationProfileEntity $entity): ConfigurationProfileEntity
    {
        $originalProfile = json_encode($entity->toArray(false), JSON_THROW_ON_ERROR);
        $this->getLogger()->debug(vsprintf('Starting saving profile: %s', [$originalProfile]));
        $entityId = $entity->getId();
        $is_insert = in_array($entityId, [0, null], true);
        $fields = $entity->toArray(false);

        unset ($fields['id']);

        $configurationsTableName = $this->getDbal()->completeTableName(ConfigurationProfileEntity::getTableName());
        if ($is_insert) {
            $storeQuery = QueryBuilder::buildInsertQuery($configurationsTableName, $fields);
        } else {
            // update
            $conditionBlock = ConditionBlock::getConditionBlock();
            $conditionBlock->addCondition(Condition::getCondition(ConditionBuilder::CONDITION_SIGN_EQ, 'id', [$entityId]));
            $storeQuery = QueryBuilder::buildUpdateQuery($configurationsTableName, $fields, $conditionBlock, ['limit' => 1]);
        }

        $this->getLogger()->debug(vsprintf('Saving profile: %s', [$storeQuery]));
        $result = $this->getDbal()->query($storeQuery);
        if (false === $result) {
            $message = vsprintf('Failed saving profile entity to database with following error message: %s',
                                [$this->getDbal()->getLastErrorMessage()]);
            $this->getLogger()->error($message);
        }

        if (true === $is_insert && false !== $result) {
            $entityFields = $entity->toArray(false);
            $entityFields['id'] = $this->getDbal()->getLastInsertedId();
            // update reference to entity
            $entity = ConfigurationProfileEntity::fromArray($entityFields, $this->getLogger());
        }

        return $entity;
    }

    public function createProfile(array $fields): ConfigurationProfileEntity
    {
        return ConfigurationProfileEntity::fromArray($fields, $this->getLogger());
    }

    public function deleteProfile(int $id): void
    {
        $configurationsTableName = $this->getDbal()->completeTableName(ConfigurationProfileEntity::getTableName());
        $this->getDbal()->queryPrepared("delete from $configurationsTableName where id = %d", $id);
    }

    protected function updateLabels(ConfigurationProfileEntity $entity): ConfigurationProfileEntity
    {
        $mainLocaleBlogId = $entity->getOriginalBlogId()->getBlogId();
        if (0 < $mainLocaleBlogId) {
            try {
                $entity->getOriginalBlogId()->setLabel($this->getSiteHelper()
                    ->getBlogLabelById($this->getPluginProxy(), $mainLocaleBlogId));
            } catch (BlogNotFoundException $e) {
                $this->getLogger()->notice("Got {$e->getMessage()}, removing profileId={$entity->getId()}");
                $entity->getOriginalBlogId()->setLabel("* deleted blog *");
                $this->deleteProfile($entity->getId());
            }
        }

        if (0 < count($entity->getTargetLocales())) {
            foreach ($entity->getTargetLocales() as $targetLocale) {
                $blogId = $targetLocale->getBlogId();
                if (0 < $blogId) {
                    try {
                        $targetLocale->setLabel($this->getSiteHelper()->getBlogLabelById($this->getPluginProxy(), $blogId));
                    } catch (BlogNotFoundException $e) {
                        $this->getLogger()->notice("Got {$e->getMessage()}, removing blogId={$targetLocale->getBlogId()} from target locales for profileId={$entity->getId()}");
                        $entity->setTargetLocales(array_filter($entity->getTargetLocales(), static function (Locale $locale) use ($blogId) {
                            return $locale->getBlogId() !== $blogId;
                        }));
                        $this->storeEntity($entity);
                    }
                }
            }
        }

        return $entity;
    }
}
