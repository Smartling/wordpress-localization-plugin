<?php

namespace KPS3\Smartling\Elementor;

use Smartling\Base\ExportedAPI;
use Smartling\Submissions\SubmissionEntity;

/**
 * Code to copy posts from one blog to another copied and adapted from https://kellenmace.com/copy-media-file-from-one-site-to-another-within-a-multisite-network/
 */
class ElementorProcessor implements RunnableInterface {
    private const DEBUG = false;

    public const FILTER_ELEMENTOR_DATA_FIELD_PROCESS = 'smartling_elementor_data_process';
    /**
     * @var SubmissionEntity
     */
    protected static $submission;
    protected array $items = [];
    /**
     * @var mixed
     */
    private $data;

    public static function register(): void
    {
        $obj = new static();

        $action = is_admin() ? 'admin_init' : 'init';
        add_action($action, static function () use ($obj) {
            $obj->run();
        }, 99);
    }

    public static function setSubmission(SubmissionEntity $submission): void
    {
        self::$submission = $submission;
    }

    public function run(): void
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, [$this, 'processElementorData'], 5, 3);
        add_filter(self::FILTER_ELEMENTOR_DATA_FIELD_PROCESS, [$this, 'mergeElementorData'], 10, 1);
    }

    public function mergeElementorData($result): array
    {
        $this->items = $result;
        self::DEBUG && print "<pre>";
        $this->injectElementorData('meta/_elementor_data/', $this->data);
        self::DEBUG && print_r($this->data, true) . PHP_EOL . PHP_EOL;
        $this->items['meta/_elementor_data'] = trim(json_encode(json_encode($this->data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), JSON_THROW_ON_ERROR), '"');
        self::DEBUG && print $this->items['meta/_elementor_data'] . PHP_EOL . PHP_EOL;
        self::DEBUG && die();

        return $this->items;
    }

    public function processElementorData($stringName, $stringValue, $submission)
    {
        if ($stringName === 'meta/_elementor_data') {
            try {
                $this->data = json_decode($stringValue, true, 512, JSON_THROW_ON_ERROR) ?: [];
            } catch (\JsonException $e) {
                if (self::DEBUG) {
                    print $e->getMessage();
                }
            }

            $this->items = [];
        }

        return $stringName;
    }

    protected function injectElementorData($prefix, &$data): array
    {
        foreach ($data as $componentIndex => $components) {
            $myPrefix = $prefix . $components['id'];
            if (isset($components['elements'])) {
                self::DEBUG && print 'START SUB:' . PHP_EOL;
                $this->injectElementorData($myPrefix . '/', $components['elements']);
                self::DEBUG && print 'END   SUB:' . PHP_EOL;
            }
            self::DEBUG && print 'CI:' . $componentIndex . PHP_EOL;
            if (isset($components['settings'])) {
                foreach ($components['settings'] as $settingIndex => $setting) {
                    self::DEBUG && print 'SI:' . $settingIndex . PHP_EOL;

                    if ($settingIndex[0] === '_') {
                        continue;
                    }
                    if (is_array($setting)) {
                        if (isset($setting['id'], $setting['url'])) {
                            $data[$componentIndex]['settings'][$settingIndex] = $this->copyImage($setting['id']);
                            self::DEBUG && print_r($data[$componentIndex]['settings'][$settingIndex]);
                        } else {
                            foreach ($setting as $optionIndex => $option) {
                                if (is_array($option)) {
                                    $options = array_filter($option, static function ($k) {
                                        return $k[0] !== '_';
                                    }, ARRAY_FILTER_USE_KEY);
                                    foreach ($options as $optionsArrayIndex => $optionValue) {
                                        self::DEBUG && print 'OV:' . $optionsArrayIndex . PHP_EOL;
                                        $key = $myPrefix . '/' . $settingIndex . '/' . $option['_id'] . '/' . $optionsArrayIndex;
                                        //!in_array($optionsArrayIndex, ElementorFilter::$IgnoreKeys) && print 'KEY1:' .  $optionsArrayIndex .' ' . $key . ' = ' . isset($this->items[$key]) . PHP_EOL;
                                        $element = &$data[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsArrayIndex];
                                        self::DEBUG && print_R($element);
                                        if (is_array($element) && isset($element['id'], $element['url'])) {
                                            $data[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsArrayIndex] = $this->copyImage($element['id']);
                                        } else if (isset($this->items[$key])) {
                                            in_array($optionsArrayIndex, ElementorFilter::$allowKeys, true) && $data[$componentIndex]['settings'][$settingIndex][$optionIndex][$optionsArrayIndex] = $this->items[$key];
                                            unset($this->items[$key]);
                                        }
                                    }
                                } else {
                                    $key = $myPrefix . '/' . $settingIndex . '/' . $optionIndex;
                                    //!in_array($optionIndex, ElementorFilter::$IgnoreKeys) &&  print 'KEY2:' . $optionIndex .' ' . $key . ' = ' . isset($this->items[ $key ]) . PHP_EOL;
                                    if (isset($this->items[$key])) {
                                        in_array($optionIndex, ElementorFilter::$allowKeys, true) && $data[$componentIndex]['settings'][$settingIndex][$optionIndex] = $this->items[$key];
                                        unset($this->items[$key]);
                                    }
                                }
                            }
                        }
                    } else {
                        $key = $myPrefix . '/' . $settingIndex;
                        if (isset ($this->items[$key])) {
                            in_array($settingIndex, ElementorFilter::$allowKeys, true) && $data[$componentIndex]['settings'][$settingIndex] = $this->items[$key];
                            unset($this->items[$key]);
                        }
                    }
                }
            }
        }
        if (self::DEBUG && count($data) > 0) {
            print "<pre>";
            print 'PROCESSSOR' . PHP_EOL;
            print_r($data);
            print PHP_EOL . '-------------------------------------' . PHP_EOL;
        }

        return $data;
    }

    /**
     * @param int $attachmentId
     *
     * @return false|mixed
     */
    protected function isDuplicate($attachmentId)
    {
        global $wpdb;
        $prep = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta where meta_key ='cloned_attachment_id' and meta_value = '%d'", $attachmentId);
        $values = $wpdb->get_col($prep);
        if (count($values) > 0) {
            return end($values);
        }

        return false;
    }

    protected function copyImage($attachmentId): array
    {
        $toBlogID = self::$submission->getTargetBlogId();

        $filePath = get_attached_file($attachmentId);
        $fileUrl = wp_get_attachment_url($attachmentId);
        $fileTypeData = wp_check_filetype(basename($filePath));
        $fileType = $fileTypeData['type'];
        $timeout = 5;

        switch_to_blog($toBlogID);

        // This prevents the image from being copied every the content is copied from smartling.
        if ( ! ($insertedID = $this->isDuplicate($attachmentId))) {
            $sideload = $this->sideloadMediaFile($fileUrl, $fileType, $timeout);
            $newPath = $sideload['file'];
            $newType = $sideload['type'];
            if ($insertedID = $this->insertMediaFile($newPath, $newType)) {
                update_post_meta($insertedID, 'cloned_attachment_id', $attachmentId);
            }
        }
        $newUrl = wp_get_attachment_url($insertedID);

        restore_current_blog();

        return [
            'url' => $newUrl,
            'id' => $insertedID,
        ];
    }

    /**
     * @return int|void|\WP_Error
     */
    protected function insertMediaFile(string $filePath = '', string $fileType = '')
    {
        if ( ! $filePath || ! $fileType) {
            return;
        }
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploadDir = wp_upload_dir();
        $attachmentData = array(
            'guid' => $uploadDir['url'] . '/' . basename($filePath),
            'post_mime_type' => $fileType,
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filePath)),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        $insertedId = wp_insert_attachment($attachmentData, $filePath);
        $insertedPath = get_attached_file($insertedId);

        $attachData = wp_generate_attachment_metadata($insertedId, $insertedPath);
        wp_update_attachment_metadata($insertedId, $attachData);

        return $insertedId;
    }

    private function sideloadMediaFile($fileUrl, $fileType, $timeout)
    {
        // Gives us access to the download_url() and wp_handle_sideload() functions.
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        // Download file to temp dir.
        $tempFile = download_url($fileUrl, $timeout);
        if (is_wp_error($tempFile)) {
            /** @var \WP_Error $tempFile */
            self::DEBUG && print 'ERROR:Count not download file ' . print_r($tempFile->get_error_messages(), true) . PHP_EOL;

            return false;
        }
        // Array based on $_FILE as seen in PHP file uploads.
        $file = array(
            'name' => basename($fileUrl),
            'type' => $fileType,
            'tmp_name' => $tempFile,
            'error' => 0,
            'size' => filesize($tempFile),
        );
        $overrides = array(
            // Tells WordPress to not look for the POST form fields that would normally be present, default is true,
            // we downloaded the file from a remote server, so there will be no form fields.
            'test_form' => false,
            // Setting this to false lets WordPress allow empty files – not recommended.
            'test_size' => true,
            // A properly uploaded file will pass this test. There should be no reason to override this one.
            'test_upload' => true,
        );

        // Move the temporary file into the uploads directory.
        return wp_handle_sideload($file, $overrides);
    }
}
