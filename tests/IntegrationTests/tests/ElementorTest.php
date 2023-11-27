<?php

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ElementorTest extends SmartlingUnitTestCaseAbstract {
    public function testElementor(): void
    {
        $contentType = 'post';
        $imageIds = [
            $this->createAttachment(),
            $this->createAttachment(),
        ];
        $postId = $this->createPostWithMeta('Elementor automated test post', '', $contentType, [
            '_elementor_data' => addslashes(vsprintf(
                str_replace('"%d"', '%d', file_get_contents(__DIR__ . '/../testdata/elementor.json')),
                $imageIds,
            )),
        ]);
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $submission = $this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $postId, $targetBlogId);
        /**
         * @var SubmissionEntity[] $images
         */
        $images = [];
        foreach ($imageIds as $imageId) {
            $images[] = $this->uploadDownload($this->getTranslationHelper()
                ->prepareSubmission('attachment', $sourceBlogId, $imageId, $targetBlogId));
        }
        $result = $this->uploadDownload($submission);
        $content = $this->getContentHelper()->readTargetContent($result);
        $this->assertEquals('[É~lém~éñtó~r áút~ómá~téd t~ést p~óst]', $content->getTitle(), 'Expected post title to be translated');
        $meta = $this->getContentHelper()->readTargetMetadata($result);
        $decoded = json_decode(json: $meta['_elementor_data'], associative: true);
        $this->assertIsArray($decoded, base64_encode($meta['_elementor_data']));
        $this->assertCount(3, $decoded, $meta['_elementor_data']);
        $this->assertEquals('<p>[L~éft t~éxt t~hréé ~síx s~évéñ]</p>', $decoded[0]['elements'][0]['elements'][0]['settings']['editor'], $meta['_elementor_data']);
        $this->assertEquals('<p>[M~íddl~é téx~t <span style="color: var( --e-global-color-text ); font-family: var( --e-global-typography-text-font-family ), Sans-serif, serif; font-weight: var( --e-global-typography-text-font-weight ); font-size: 2.1rem;">th~réé s~íx sé~véñ</span>]</p>', $decoded[0]['elements'][1]['elements'][0]['settings']['editor'], $meta['_elementor_data']);
        $this->assertEquals('[R~ígh~t héá~díñg ~ th~réé s~íx sé~véñ]', $decoded[0]['elements'][2]['elements'][0]['settings']['title'], $meta['_elementor_data']);
        $this->assertEquals($images[0]->getTargetId(), $decoded[1]['elements'][0]['elements'][0]['settings']['image']['id'], $meta['_elementor_data']);
        $this->assertEquals($images[1]->getTargetId(), $decoded[2]['elements'][0]['elements'][0]['settings']['image']['id'], $meta['_elementor_data']);
        foreach ($images as $key => $image) {
            $this->assertNotEquals($image->getSourceId(), $image->getTargetId(), "Image $key has same source and target ids");
        }
    }
}
