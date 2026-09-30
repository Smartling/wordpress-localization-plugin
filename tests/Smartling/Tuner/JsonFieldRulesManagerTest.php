<?php

namespace Smartling\Tests\Smartling\Tuner;

use PHPUnit\Framework\TestCase;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\JsonFieldRule;
use Smartling\Tuner\JsonFieldRulesManager;

class JsonFieldRulesManagerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    public function testStorageKey(): void
    {
        $this->assertSame(JsonFieldRulesManager::STORAGE_KEY, (new JsonFieldRulesManager())->getStorageKey());
    }

    public function testAddAndListItems(): void
    {
        $m = new JsonFieldRulesManager();
        $id = $m->add([
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'copy',
        ]);
        $this->assertNotSame('', $id);

        $items = $m->listItems();
        $this->assertArrayHasKey($id, $items);
        $this->assertInstanceOf(JsonFieldRule::class, $items[$id]);
        $this->assertSame('_elementor_data', $items[$id]->getMetaKey());
        $this->assertSame('$.x', $items[$id]->getPropertyPath());
        $this->assertSame('copy', $items[$id]->getReplacerId());
    }

    public function testAddDoesNotDuplicate(): void
    {
        $m = new JsonFieldRulesManager();
        $data = [
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'copy',
        ];
        $id1 = $m->add($data);
        $id2 = $m->add($data);
        $this->assertNotSame('', $id1);
        $this->assertSame('', $id2);
        $this->assertCount(1, $m->listItems());
    }

    public function testRemoveItem(): void
    {
        $m = new JsonFieldRulesManager();
        $id = $m->add(['metaKey' => '_elementor_data', 'propertyPath' => '$.a', 'replacerId' => 'copy']);
        $this->assertCount(1, $m->listItems());

        $m->removeItem($id);

        $this->assertCount(0, $m->listItems());
    }

    public function testExportOmitsIdsAndIncludesExtendedFields(): void
    {
        $m = new JsonFieldRulesManager();
        $m->add(['metaKey' => 'm', 'propertyPath' => '$.x', 'replacerId' => 'copy']);
        $m->add(['metaKey' => 'm', 'propertyPath' => '$..y', 'replacerId' => 'translate', 'widgetType' => 'w']);

        $export = $m->export();

        $this->assertSame(JsonFieldRulesManager::EXPORT_FORMAT_VERSION, $export['version']);
        $this->assertSame([
            ['metaKey' => 'm', 'propertyPath' => '$.x', 'replacerId' => 'copy'],
            ['metaKey' => 'm', 'propertyPath' => '$..y', 'replacerId' => 'translate', 'matchMode' => 'anywhere', 'widgetType' => 'w'],
        ], $export['rules']);
    }

    public function testImportPreservesExistingRulesAndSkipsDuplicates(): void
    {
        $m = new JsonFieldRulesManager();
        $existingId = $m->add(['metaKey' => 'm', 'propertyPath' => '$.keep', 'replacerId' => 'copy']);
        $legacyId = $m->add(['contentType' => 'page', 'metaKey' => 'm', 'propertyPath' => '$.legacy', 'replacerId' => 'translate']);

        $result = $m->import([
            new JsonFieldRule('m', '$.keep', 'copy'),
            new JsonFieldRule('m', '$.legacy', 'translate'),
            new JsonFieldRule('m', '$.new', 'translate'),
            new JsonFieldRule('m', '$.new', 'translate'),
            new JsonFieldRule('m', '$..scoped', 'translate', 'w'),
        ]);

        $this->assertSame(['added' => 2, 'skipped' => 3], $result);
        $items = $m->listItems();
        $this->assertCount(4, $items);
        $this->assertSame('$.keep', $items[$existingId]->getPropertyPath());
        $this->assertSame('$.legacy', $items[$legacyId]->getPropertyPath());
    }

    public function testExportThenImportIntoEmptyManagerRoundTrips(): void
    {
        $source = new JsonFieldRulesManager();
        $source->add(['metaKey' => 'm', 'propertyPath' => '$..y', 'replacerId' => 'related|attachment', 'conditions' => [['ancestor' => 0, 'key' => 'k', 'value' => 'v']]]);
        $target = new JsonFieldRulesManager();

        $target->import(array_map(JsonFieldRule::fromArray(...), $source->export()['rules']));

        $this->assertEquals(array_values($source->listItems()), array_values($target->listItems()));
    }
}
