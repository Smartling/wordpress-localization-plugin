<?php /** @noinspection PhpUnusedParameterInspection */

namespace {

    use Smartling\DbAl\MultiligualPressConnector;

    function injectMocks()
    {
        /**
         * Constants
         */
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');

        $_SERVER['HTTP_HOST'] = 'test.com';

        /**
         * Functions
         */

        if (!function_exists('get_current_user_id')) {
            function get_current_user_id()
            {
                return 0;
            }
        }

        if (!function_exists('__')) {
            function __($text, $scope = '')
            {
                return $text;
            }
        }

        if (!function_exists('get_current_blog_id')) {
            function get_current_blog_id()
            {
                return 1;
            }
        }

        if (!function_exists('get_current_site')) {
            function get_current_site()
            {
                return (object)['id' => 1];
            }
        }

        if (!function_exists('wp_get_current_user')) {
            function wp_get_current_user()
            {
                return (object)['user_login' => 1];
            }
        }

        if (!function_exists('wp_get_sites')) {
            function wp_get_sites()
            {
                return [['site_id' => 1, 'blog_id' => 1,], ['site_id' => 1, 'blog_id' => 2,],
                        ['site_id' => 1, 'blog_id' => 3,], ['site_id' => 1, 'blog_id' => 4,],];
            }
        }

        if (!function_exists('ms_is_switched')) {
            function ms_is_switched()
            {
                return true;
            }
        }

        if (!function_exists('restore_current_blog')) {
            function restore_current_blog()
            {
                return true;
            }
        }

        if (!function_exists('switch_to_blog')) {
            function switch_to_blog($blogId)
            {
                return true;
            }
        }

        if (!function_exists('get_site_option')) {
            function get_site_option($key, $default = null, $useCache = true)
            {
                switch ($key) {
                    case MultiligualPressConnector::MULTILINGUAL_PRESS_PRO_SITE_OPTION: {
                        return [1 => ['text' => '', 'lang' => 'en_US',], 2 => ['text' => '', 'lang' => 'es_ES',],
                                3 => ['text' => '', 'lang' => 'fr_FR',], 4 => ['text' => '', 'lang' => 'ru_RU',],];
                        break;
                    }
                }

            }
        }

        if (!function_exists('update_site_option')) {
            function update_site_option($key, $value) {
            }
        }

        if (!function_exists('delete_site_option')) {
            function delete_site_option($key) {
            }
        }

        if (!function_exists('is_wp_error')) {
            function is_wp_error($something)
            {
                return false;
            }
        }

        if (!function_exists('get_bloginfo')) {
            function get_bloginfo($part)
            {
                return $part;
            }
        }

        if (!function_exists('get_post')) {
            function get_post($id, $output)
            {

                $date = Smartling\Helpers\DateTimeHelper::nowAsString();

                $type = $id < 10 ? 'post' : 'page';

                $post = ['ID'            => $id, 'post_author' => 1, 'post_date' => $date, 'post_date_gmt' => $date,
                         'post_content'  => 'Test content', 'post_title' => 'Here goes the title', 'post_excerpt' => '',
                         'post_status'   => 'published', 'comment_status' => 'open', 'ping_status' => '',
                         'post_password' => '', 'post_name' => 'Here goes the title', 'to_ping' => '', 'pinged' => '',
                         'post_modified' => $date, 'post_modified_gmt' => $date, 'post_content_filtered' => '',
                         'post_parent'   => 0, 'guid' => '/here-goes-the-title', 'menu_order' => 0,
                         'post_type'     => $type, 'post_mime_type' => 'post', 'comment_count' => 0,];

                return $post;
            }
        }

        if (!function_exists('wp_insert_post')) {
            function wp_insert_post(array $fields, $returnError)
            {
                return $fields['ID'] ? : 2;
            }
        }

        if (!function_exists('get_post_meta')) {
            function get_post_meta($postId)
            {
                return ['meta1' => ['value1'], 'meta2' => ['value2'], 'meta3' => ['value3'],];
            }
        }

        if (!function_exists('get_post_types')) {
            function get_post_types()
            {
                return ['acf-field' => '', 'acf-field-group' => ''];
            }
        }

        if (!function_exists('metadata_exists')) {
            function metadata_exists($meta_type, $object_id, $meta_key)
            {
                return rand(0, 9) >= 5;
            }
        }

        if (!function_exists('add_post_meta')) {
            function add_post_meta($post_id, $meta_key, $meta_value, $unique = false)
            {
                return true;
            }
        }

        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
            {
                return true;
            }
        }

        if (!function_exists('wp_update_term')) {
            function wp_update_term($id, $type, $args)
            {
                return array_merge($args, ['term_id' => $id]);
            }
        }
        if (!function_exists('wp_insert_term')) {
            function wp_insert_term($name, $type, $args)
            {
                return array_merge($args, ['term_id' => 2]);
            }
        }
        if (!function_exists('get_term')) {
            function get_term($id, $taxonomy, $outputFormat)
            {
                $category = ['term_id'          => $id, 'name' => 'Fake Name', 'slug' => 'fake-name', 'term_group' => 0,
                             'term_taxonomy_id' => 0, 'taxonomy' => $taxonomy, 'description' => '', 'parent' => 0,
                             'count'            => 0,];


                return $category;
            }
        }

        if (!function_exists('add_action')) {
            function add_action($action, $handler)
            {
            }
        }

        if (!function_exists('add_filter')) {
            function add_filter($action, $handler)
            {
            }
        }

        if (!function_exists('maybe_unserialize')) {
            function maybe_unserialize($original)
            {
                if (is_serialized($original)) {
                    return @unserialize($original);
                }

                return $original;
            }
        }

        if (!function_exists('is_serialized')) {
            function is_serialized($data, $strict = true)
            {
                // if it isn't a string, it isn't serialized.
                if (!is_string($data)) {
                    return false;
                }
                $data = trim($data);
                if ('N;' == $data) {
                    return true;
                }
                if (strlen($data) < 4) {
                    return false;
                }
                if (':' !== $data[1]) {
                    return false;
                }
                if ($strict) {
                    $lastc = substr($data, -1);
                    if (';' !== $lastc && '}' !== $lastc) {
                        return false;
                    }
                } else {
                    $semicolon = strpos($data, ';');
                    $brace = strpos($data, '}');
                    // Either ; or } must exist.
                    if (false === $semicolon && false === $brace) {
                        return false;
                    }
                    // But neither must be in the first X characters.
                    if (false !== $semicolon && $semicolon < 3) {
                        return false;
                    }
                    if (false !== $brace && $brace < 4) {
                        return false;
                    }
                }
                $token = $data[0];
                switch ($token) {
                    case 's' :
                        if ($strict) {
                            if ('"' !== substr($data, -2, 1)) {
                                return false;
                            }
                        } elseif (false === strpos($data, '"')) {
                            return false;
                        }
                    // or else fall through
                    case 'a' :
                    case 'O' :
                        return (bool)preg_match("/^{$token}:[0-9]+:/s", $data);
                    case 'b' :
                    case 'i' :
                    case 'd' :
                        $end = $strict ? '$' : '';

                        return (bool)preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
                }

                return false;
            }
        }

        if (!function_exists('do_action')) {
            function do_action($a, $b)
            {
            }
        }

        if (!function_exists('wp_get_object_terms')) {
            function wp_get_object_terms($a, $b)
            {
            }
        }

        if (!function_exists('wp_set_post_terms')) {
            function wp_set_post_terms($a, $b)
            {
            }
        }

        if (!function_exists('add_filter')) {
            function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
            {
            }
        }

        if (!function_exists('wp_get_post_categories')) {
            function wp_get_post_categories($id)
            {
                return [1, 2, 3];
            }
        }
    }
}
namespace Smartling\Tests\Mocks {


    class WordpressFunctionsMockHelper
    {
        public static function injectFunctionsMocks()
        {
            injectMocks();
        }
    }
}