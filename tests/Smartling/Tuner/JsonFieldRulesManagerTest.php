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
}
