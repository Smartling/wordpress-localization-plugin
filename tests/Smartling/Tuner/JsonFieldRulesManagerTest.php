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
            'contentType' => 'page',
            'metaKey' => '_elementor_data',
            'propertyPath' => '$.x',
            'replacerId' => 'copy',
        ]);
        $this->assertNotSame('', $id);

        $items = $m->listItems();
        $this->assertArrayHasKey($id, $items);
        $this->assertInstanceOf(JsonFieldRule::class, $items[$id]);
        $this->assertSame('page', $items[$id]->getContentType());
        $this->assertSame('_elementor_data', $items[$id]->getMetaKey());
        $this->assertSame('$.x', $items[$id]->getPropertyPath());
        $this->assertSame('copy', $items[$id]->getReplacerId());
    }

    public function testAddDoesNotDuplicate(): void
    {
        $m = new JsonFieldRulesManager();
        $data = [
            'contentType' => 'page',
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

    public function testGetRulesForFiltersByContentTypeAndMetaKey(): void
    {
        $m = new JsonFieldRulesManager();
        $m->add(['contentType' => 'page', 'metaKey' => '_elementor_data', 'propertyPath' => '$.a', 'replacerId' => 'copy']);
        $m->add(['contentType' => 'post', 'metaKey' => '_elementor_data', 'propertyPath' => '$.b', 'replacerId' => 'copy']);
        $m->add(['contentType' => 'page', 'metaKey' => '_other_field',    'propertyPath' => '$.c', 'replacerId' => 'copy']);

        $rules = $m->getRulesFor('page', '_elementor_data');

        $this->assertCount(1, $rules);
        $rule = array_values($rules)[0];
        $this->assertSame('$.a', $rule->getPropertyPath());
    }

    public function testGetRulesForWildcardContentType(): void
    {
        $m = new JsonFieldRulesManager();
        $m->add(['contentType' => '*', 'metaKey' => '_elementor_data', 'propertyPath' => '$.a', 'replacerId' => 'copy']);

        $this->assertCount(1, $m->getRulesFor('page', '_elementor_data'));
        $this->assertCount(1, $m->getRulesFor('post', '_elementor_data'));
        $this->assertCount(0, $m->getRulesFor('page', '_other'));
    }

    public function testRemoveItem(): void
    {
        $m = new JsonFieldRulesManager();
        $id = $m->add(['contentType' => 'page', 'metaKey' => '_elementor_data', 'propertyPath' => '$.a', 'replacerId' => 'copy']);
        $this->assertCount(1, $m->listItems());

        $m->removeItem($id);

        $this->assertCount(0, $m->listItems());
    }
}
