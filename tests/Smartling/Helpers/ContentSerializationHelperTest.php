<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\RuntimeCacheHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

class ContentSerializationHelperTest extends TestCase
{
    private function helper(array $entity): ContentSerializationHelper
    {
        $content = $this->createMock(Entity::class);
        $content->method('toArray')->willReturn($entity);
        $contentHelper = $this->createMock(ContentHelper::class);
        $contentHelper->method('readSourceContent')->willReturn($content);
        $contentHelper->method('readSourceMetadata')->willReturn(['meta' => 'value']);

        return new ContentSerializationHelper($contentHelper, $this->createMock(SettingsManager::class));
    }

    private function submission(int $id): SubmissionEntity
    {
        $submission = new SubmissionEntity();
        $submission->setSourceBlogId(1);
        $submission->setContentType('post');
        $submission->setSourceId($id);

        return $submission;
    }

    public function testPublishingDoesNotChangeHash(): void
    {
        $draft = ['post_title' => 'title', 'post_content' => 'content', 'post_status' => 'draft', 'post_name' => ''];
        $published = ['post_title' => 'title', 'post_content' => 'content', 'post_status' => 'publish', 'post_name' => 'title'];

        $draftHash = $this->helper($draft)->calculateHash($this->submission(101));
        $publishedHash = $this->helper($published)->calculateHash($this->submission(102));

        $this->assertSame($draftHash, $publishedHash);
    }

    public function testContentChangeChangesHash(): void
    {
        $this->assertNotSame(
            $this->helper(['post_content' => 'a', 'post_status' => 'draft'])->calculateHash($this->submission(111)),
            $this->helper(['post_content' => 'b', 'post_status' => 'draft'])->calculateHash($this->submission(112)),
        );
    }
}
