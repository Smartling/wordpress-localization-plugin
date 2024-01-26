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
            '_elementor_edit_mode' => 'builder',
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
        $contentArray = $content->toArray();
        $this->assertArrayHasKey('post_content', $contentArray, json_encode($contentArray));
        $this->assertEquals('<p>[L~éft t~éxt t~hréé ~síx s~évéñ]</p><p>[M~íddl~é téx~t th~réé s~íx sé~véñ]</p>		
			<h2>[R~ígh~t héá~díñg ~ th~réé s~íx sé~véñ]</h2>		
															<img width="300" height="225" src="http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-6-300x225.jpg" alt="" loading="lazy" srcset="http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-6-300x225.jpg 300w, http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-6.jpg 640w" sizes="(max-width: 300px) 100vw, 300px" />															
															<img width="640" height="480" src="http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-7.jpg" alt="" loading="lazy" srcset="http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-7.jpg 640w, http://test.com/WP_INSTALL_DIR/wp-content/uploads/sites/2/2024/01/canola-7-300x225.jpg 300w" sizes="(max-width: 640px) 100vw, 640px" />', $contentArray['post_content']);
    }

    public function testElementorComplexContent(): void
    {
        $contentType = 'post';
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $css = 'a:6:{s:4:"time";i:1700511753;s:5:"fonts";a:0:{}s:5:"icons";a:3:{i:0;s:0:"";i:1;s:3:"svg";i:7;s:8:"fa-light";}s:20:"dynamic_elements_ids";a:0:{}s:6:"status";s:4:"file";i:0;s:0:"";}';
        $postId = $this->createPostWithMeta('Elementor automated test post', '', $contentType, [
            '_elementor_css' => $css,
            '_elementor_data' => addslashes(file_get_contents(__DIR__ . '/../testdata/wp-860-source.json')),
        ]);
        $submission = $this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $postId, $targetBlogId);
        $result = $this->uploadDownload($submission);
        $meta = $this->getContentHelper()->readTargetMetadata($result);
        $decoded = json_decode(json: $meta['_elementor_data'], associative: true);
        $this->assertEquals($css, $meta['_elementor_css']);
        $this->assertIsArray($decoded, base64_encode($meta['_elementor_data']));
        $this->assertCount(12, $decoded, $meta['_elementor_data']);
        $this->assertEquals(json_decode(json: file_get_contents(__DIR__ . '/../testdata/wp-860-expected.json'), associative: true), $decoded);
    }

    public function testElementorPopups(): void
    {
        $contentType = 'post';
        $sourceBlogId = 1;
        $targetBlogId = 2;
        $popupId = $this->createPost(title: "Popup");
        $this->assertIsInt($popupId);
        $popupSubmission = $this->uploadDownload($this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $popupId, $targetBlogId));
        $this->assertNotNull($popupSubmission);
        $this->assertNotEquals(0, $popupSubmission->getTargetId());
        $json = str_replace('popupId', $popupId, file_get_contents(__DIR__ . '/../testdata/wp-863-source.json'));
        $postId = $this->createPostWithMeta('Elementor automated test post', '', $contentType, [
            '_elementor_data' => addslashes($json),
        ]);
        $this->assertIsInt($postId);
        $submission = $this->getTranslationHelper()->prepareSubmission($contentType, $sourceBlogId, $postId, $targetBlogId);
        $result = $this->uploadDownload($submission);
        $meta = $this->getContentHelper()->readTargetMetadata($result);
        $decoded = json_decode(json: $meta['_elementor_data'], associative: true);
        $this->assertIsArray($decoded, base64_encode($meta['_elementor_data']));
        $this->assertEquals(json_decode(json: str_replace('popupId', $popupSubmission->getTargetId(), file_get_contents(__DIR__ . '/../testdata/wp-863-expected.json')), associative: true), $decoded);
    }
}
