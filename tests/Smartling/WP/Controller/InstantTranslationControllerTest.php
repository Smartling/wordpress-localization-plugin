<?php

namespace Smartling\WP\Controller;

use PHPUnit\Framework\TestCase;
use Smartling\FTS\FtsService;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionFactory;
use Smartling\Submissions\SubmissionManager;

class InstantTranslationControllerTest extends TestCase
{
    private InstantTranslationController $controller;
    private FtsService $ftsService;
    private SubmissionManager $submissionManager;
    private SubmissionFactory $submissionFactory;
    private FileUriHelper $fileUriHelper;
    private WordpressFunctionProxyHelper $wpProxy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ftsService = $this->createMock(FtsService::class);
        $this->submissionManager = $this->createMock(SubmissionManager::class);
        $this->submissionFactory = $this->createMock(SubmissionFactory::class);
        $this->fileUriHelper = $this->createMock(FileUriHelper::class);
        $this->wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);

        $this->controller = new InstantTranslationController(
            $this->ftsService,
            $this->submissionManager,
            $this->submissionFactory,
            $this->fileUriHelper,
            $this->wpProxy
        );
    }

    public function testCountRelatedItemsWithEmptyRelations(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('countRelatedItems');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, []);
        $this->assertEquals(0, $result);
    }

    public function testCountRelatedItemsWithSingleTarget(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('countRelatedItems');
        $method->setAccessible(true);

        $relations = [
            2 => [
                'attachment' => [1, 2],
                'category' => [5]
            ]
        ];

        $result = $method->invoke($this->controller, $relations);
        $this->assertEquals(3, $result); // 2 attachments + 1 category
    }

    public function testCountRelatedItemsWithMultipleTargets(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('countRelatedItems');
        $method->setAccessible(true);

        $relations = [
            2 => [
                'attachment' => [1, 2],
                'category' => [5]
            ],
            3 => [
                'attachment' => [1, 2], // Same items in different target
                'tag' => [10]
            ]
        ];

        // Should deduplicate - unique items are: attachment:1, attachment:2, category:5, tag:10
        $result = $method->invoke($this->controller, $relations);
        $this->assertEquals(4, $result);
    }

    public function testGetRelatedSourcesExcludesMainContent(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getRelatedSources');
        $method->setAccessible(true);

        $relations = [
            2 => [
                'post' => [123, 456], // 123 is main content
                'attachment' => [1]
            ]
        ];

        $result = $method->invoke($this->controller, $relations, 2, 'post', 123);

        $this->assertCount(2, $result);
        $this->assertEquals(['id' => 456, 'type' => 'post'], $result[0]);
        $this->assertEquals(['id' => 1, 'type' => 'attachment'], $result[1]);
    }

    public function testGetRelatedSourcesWithNoMatchingTarget(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getRelatedSources');
        $method->setAccessible(true);

        $relations = [
            2 => [
                'post' => [123],
            ]
        ];

        // Request for target blog 3, which doesn't exist in relations
        $result = $method->invoke($this->controller, $relations, 3, 'post', 123);

        $this->assertCount(0, $result);
    }

    public function testGetOrCreateSubmissionCreatesNew(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getOrCreateSubmission');
        $method->setAccessible(true);

        // No existing submission found
        $this->submissionManager->method('findOne')->willReturn(null);

        // Factory should create new submission
        $newSubmission = $this->createMock(SubmissionEntity::class);
        $newSubmission->method('setFileUri')->willReturnSelf();
        $newSubmission->method('setStatus')->willReturnSelf();

        $this->submissionFactory->method('fromArray')->willReturn($newSubmission);
        $this->fileUriHelper->method('generateFileUri')->willReturn('file://test.xml');

        // Manager should store it
        $this->submissionManager->method('storeEntity')->willReturn($newSubmission);

        $result = $method->invoke($this->controller, 1, 2, 'post', 123);

        $this->assertInstanceOf(SubmissionEntity::class, $result);
    }

    public function testGetOrCreateSubmissionReusesExisting(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getOrCreateSubmission');
        $method->setAccessible(true);

        // Existing submission found
        $existingSubmission = $this->createMock(SubmissionEntity::class);
        $existingSubmission->method('setStatus')->willReturnSelf();

        $this->submissionManager->method('findOne')->willReturn($existingSubmission);
        $this->submissionManager->method('storeEntity')->willReturn($existingSubmission);

        $result = $method->invoke($this->controller, 1, 2, 'post', 123);

        $this->assertInstanceOf(SubmissionEntity::class, $result);
        $this->assertSame($existingSubmission, $result);
    }

    public function testBuildSubmissionsWithNoRelations(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildSubmissions');
        $method->setAccessible(true);

        // Setup mocks for main content only
        $this->submissionManager->method('findOne')->willReturn(null);

        $mainSubmission = $this->createMock(SubmissionEntity::class);
        $mainSubmission->method('setFileUri')->willReturnSelf();
        $mainSubmission->method('setStatus')->willReturnSelf();

        $this->submissionFactory->method('fromArray')->willReturn($mainSubmission);
        $this->fileUriHelper->method('generateFileUri')->willReturn('file://test.xml');
        $this->submissionManager->method('storeEntity')->willReturn($mainSubmission);

        $result = $method->invoke(
            $this->controller,
            'post',
            123,
            1,
            [2, 3],
            [] // No relations
        );

        // Should create 2 submissions (1 main content × 2 target blogs)
        $this->assertCount(2, $result);
    }

    public function testBuildSubmissionsWithRelations(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildSubmissions');
        $method->setAccessible(true);

        // Setup mocks
        $this->submissionManager->method('findOne')->willReturn(null);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('setFileUri')->willReturnSelf();
        $submission->method('setStatus')->willReturnSelf();

        $this->submissionFactory->method('fromArray')->willReturn($submission);
        $this->fileUriHelper->method('generateFileUri')->willReturn('file://test.xml');
        $this->submissionManager->method('storeEntity')->willReturn($submission);

        $relations = [
            2 => [
                'attachment' => [1, 2]
            ],
            3 => [
                'attachment' => [1]
            ]
        ];

        $result = $method->invoke(
            $this->controller,
            'post',
            123,
            1,
            [2, 3],
            $relations
        );

        // Should create:
        // - Blog 2: 1 main + 2 attachments = 3 submissions
        // - Blog 3: 1 main + 1 attachment = 2 submissions
        // Total: 5 submissions
        $this->assertCount(5, $result);
    }

    public function testBuildSubmissionsExcludesMainContentFromRelations(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('buildSubmissions');
        $method->setAccessible(true);

        // Setup mocks
        $this->submissionManager->method('findOne')->willReturn(null);

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('setFileUri')->willReturnSelf();
        $submission->method('setStatus')->willReturnSelf();

        $this->submissionFactory->method('fromArray')->willReturn($submission);
        $this->fileUriHelper->method('generateFileUri')->willReturn('file://test.xml');
        $this->submissionManager->method('storeEntity')->willReturn($submission);

        $relations = [
            2 => [
                'post' => [123, 456], // 123 is the main content, should be excluded from relations
                'attachment' => [1]
            ]
        ];

        $result = $method->invoke(
            $this->controller,
            'post',
            123,
            1,
            [2],
            $relations
        );

        // Should create:
        // - 1 main (post 123)
        // - 1 related post (456)
        // - 1 attachment (1)
        // Total: 3 submissions (NOT 4, since post 123 shouldn't be duplicated)
        $this->assertCount(3, $result);
    }

    public function testHandleRequestTranslationWithMissingParameters(): void
    {
        // Simulate AJAX environment with missing contentType
        $_POST = [
            'contentId' => 123,
            'targetBlogIds' => [2, 3],
        ];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);
        $this->wpProxy->method('sanitize_text_field')->willReturn('');
        $this->wpProxy->method('wp_unslash')->willReturnArgument(0);
        $this->wpProxy->method('map_deep')->willReturnArgument(0);

        // Expect error response
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Missing required parameters: contentType, contentId, or targetBlogIds';
                }),
                400
            );

        $this->controller->handleRequestTranslation();
    }

    public function testHandleRequestTranslationWithEmptyTargetBlogIds(): void
    {
        // Simulate AJAX environment with empty targetBlogIds
        $_POST = [
            'contentType' => 'post',
            'contentId' => 123,
            'targetBlogIds' => [],
        ];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);
        $this->wpProxy->method('sanitize_text_field')->willReturn('post');
        $this->wpProxy->method('wp_unslash')->willReturnArgument(0);
        $this->wpProxy->method('map_deep')->willReturnArgument(0);

        // Expect error response
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Missing required parameters: contentType, contentId, or targetBlogIds';
                }),
                400
            );

        $this->controller->handleRequestTranslation();
    }

    public function testHandleRequestTranslationWithFailedSubmissionCreation(): void
    {
        // Simulate AJAX environment
        $_POST = [
            'contentType' => 'post',
            'contentId' => 123,
            'targetBlogIds' => [2],
            'relations' => [],
        ];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);
        $this->wpProxy->method('sanitize_text_field')->willReturn('post');
        $this->wpProxy->method('wp_unslash')->willReturnArgument(0);
        $this->wpProxy->method('map_deep')->willReturnArgument(0);
        $this->wpProxy->method('get_current_blog_id')->willReturn(1);

        // Mock submission creation failure (throws exception)
        $this->submissionManager->method('findOne')->willReturn(null);
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('setFileUri')->willReturnSelf();
        $submission->method('setStatus')->willReturnSelf();
        $this->submissionFactory->method('fromArray')->willReturn($submission);
        $this->fileUriHelper->method('generateFileUri')->willReturn('test.xml');
        $this->submissionManager->method('storeEntity')->willThrowException(new \Exception('Database error'));

        // Expect error response for failed submission creation
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Failed to create submissions for translation';
                }),
                500
            );

        $this->controller->handleRequestTranslation();
    }

    public function testHandlePollStatusWithInvalidSubmissionId(): void
    {
        // Simulate AJAX environment with invalid submission ID
        $_POST = ['submissionId' => 0];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);

        // Expect error response
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Invalid submission ID';
                }),
                400
            );

        $this->controller->handlePollStatus();
    }

    public function testHandlePollStatusWithNegativeSubmissionId(): void
    {
        // Simulate AJAX environment with negative submission ID
        $_POST = ['submissionId' => -5];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);

        // Expect error response
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Invalid submission ID';
                }),
                400
            );

        $this->controller->handlePollStatus();
    }

    public function testHandlePollStatusWithNotFoundSubmission(): void
    {
        // Simulate AJAX environment
        $_POST = ['submissionId' => 999];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);

        // Mock submission not found
        $this->submissionManager->method('getEntityById')->with(999)->willReturn(null);

        // Expect error response
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_error')
            ->with(
                $this->callback(function ($data) {
                    return $data['message'] === 'Submission not found';
                }),
                404
            );

        $this->controller->handlePollStatus();
    }

    public function testHandlePollStatusReturnsCorrectFormat(): void
    {
        // Simulate AJAX environment
        $_POST = ['submissionId' => 123];

        // Mock nonce and permission checks pass
        $this->wpProxy->method('check_ajax_referer')->willReturn(true);
        $this->wpProxy->method('current_user_can')->willReturn(true);

        // Mock submission found
        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getStatus')->willReturn(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
        $submission->method('getCompletionPercentage')->willReturn(100);
        $submission->method('getLastError')->willReturn('');

        $this->submissionManager->method('getEntityById')->with(123)->willReturn($submission);

        // Expect success response with correct format
        $this->wpProxy->expects($this->once())
            ->method('wp_send_json_success')
            ->with($this->callback(function ($data) {
                return isset($data['status']) &&
                       isset($data['progress']) &&
                       isset($data['message']) &&
                       $data['status'] === 'completed' &&
                       $data['progress'] === 100;
            }));

        $this->controller->handlePollStatus();
    }

    public function testMapSubmissionStatusReturnsCorrectValues(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapSubmissionStatus');
        $method->setAccessible(true);

        $this->assertEquals('completed', $method->invoke($this->controller, SubmissionEntity::SUBMISSION_STATUS_COMPLETED));
        $this->assertEquals('failed', $method->invoke($this->controller, SubmissionEntity::SUBMISSION_STATUS_FAILED));
        $this->assertEquals('failed', $method->invoke($this->controller, SubmissionEntity::SUBMISSION_STATUS_CANCELLED));
        $this->assertEquals('in_progress', $method->invoke($this->controller, SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS));
        $this->assertEquals('pending', $method->invoke($this->controller, SubmissionEntity::SUBMISSION_STATUS_NEW));
        $this->assertEquals('pending', $method->invoke($this->controller, 'unknown_status'));
    }
}
