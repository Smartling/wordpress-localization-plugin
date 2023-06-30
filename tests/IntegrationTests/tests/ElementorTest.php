<?php

use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ElementorTest extends SmartlingUnitTestCaseAbstract {
    public function testElementor(): void
    {
        $contentType = 'post';
        $imageIds = [$this->createAttachment(), $this->createAttachment()];
        $postId = $this->createPostWithMeta('Elementor automated test post', '', $contentType, [
            '_elementor_data' => addslashes(<<<JSON
[{"id":"590657a","elType":"section","settings":{"structure":"30"},"elements":[{"id":"b56da21","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"c799791","elType":"widget","settings":{"editor":"<p>Left text three six seven<\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"0f3ad3c","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"0088b31","elType":"widget","settings":{"editor":"<p>Middle text\u00a0<span style=\"color: var( --e-global-color-text ); font-family: var( --e-global-typography-text-font-family ), Sans-serif, serif; font-weight: var( --e-global-typography-text-font-weight ); font-size: 2.1rem;\">three six seven<\/span><\/p>"},"elements":[],"widgetType":"text-editor"}],"isInner":false},{"id":"8798127","elType":"column","settings":{"_column_size":33,"_inline_size":null},"elements":[{"id":"78d53a1","elType":"widget","settings":{"title":"Right heading &nbsp;three six seven"},"elements":[],"widgetType":"heading"}],"isInner":false}],"isInner":false},{"id":"7a874c7","elType":"section","settings":[],"elements":[{"id":"d7d603e","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"ea10188","elType":"widget","settings":{"image":{"url":"http:\/\/example.com\/wp-content\/uploads\/2023\/06\/elementor-image.png","id":$imageIds[0],"alt":"","source":"library"},"image_size":"medium"},"elements":[],"widgetType":"image"}],"isInner":false}],"isInner":false},{"id":"6334fe1","elType":"section","settings":[],"elements":[{"id":"27c6d9f","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"d9ecb93","elType":"widget","settings":{"image":{"url":"http:\/\/example.com\/wp-content\/uploads\/2023\/06\/20220726-1.jpeg","id":$imageIds[1],"alt":"","source":"library"}},"elements":[],"widgetType":"image"}],"isInner":false}],"isInner":false}]
JSON
            ),
        ]);
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $submission = $this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $postId, $targetBlogId);
        /**
         * @var SubmissionEntity[] $images
         */
        $images = [
            $this->uploadDownload($this->getTranslationHelper()->prepareSubmission('attachment', $sourceBlogId, $imageIds[0], $targetBlogId)),
            $this->uploadDownload($this->getTranslationHelper()->prepareSubmission('attachment', $sourceBlogId, $imageIds[1], $targetBlogId)),
        ];
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
