<?php

/**
 * factories
 */

namespace {

    require_once ABSPATH . '/wp-admin/includes/image.php';

    abstract class WP_UnitTest_Factory_For_Thing
    {

        var $default_generation_definitions;
        var $factory;

        /**
         * Creates a new factory, which will create objects of a specific Thing
         *
         * @param object $factory                        Global factory that can be used to create other objects on the
         *                                               system
         * @param array  $default_generation_definitions Defines what default values should the properties of the
         *                                               object have. The default values can be generators -- an object
         *                                               with next() method. There are some default generators:
         *                                               {@link WP_UnitTest_Generator_Sequence},
         *                                               {@link WP_UnitTest_Generator_Locale_Name},
         *                                               {@link WP_UnitTest_Factory_Callback_After_Create}.
         */
        function __construct($factory, $default_generation_definitions = array())
        {
            $this->factory = $factory;
            $this->default_generation_definitions = $default_generation_definitions;
        }

        abstract function create_object($args);

        abstract function update_object($object, $fields);

        function create($args = array(), $generation_definitions = null)
        {
            if (is_null($generation_definitions)) {
                $generation_definitions = $this->default_generation_definitions;
            }

            $generated_args = $this->generate_args($args, $generation_definitions, $callbacks);
            $created = $this->create_object($generated_args);
            if (!$created || is_wp_error($created)) {
                return $created;
            }

            if ($callbacks) {
                $updated_fields = $this->apply_callbacks($callbacks, $created);
                $save_result = $this->update_object($created, $updated_fields);
                if (!$save_result || is_wp_error($save_result)) {
                    return $save_result;
                }
            }

            return $created;
        }

        function create_and_get($args = array(), $generation_definitions = null)
        {
            $object_id = $this->create($args, $generation_definitions);

            return $this->get_object_by_id($object_id);
        }

        abstract function get_object_by_id($object_id);

        function create_many($count, $args = array(), $generation_definitions = null)
        {
            $results = array();
            for ($i = 0; $i < $count; $i++) {
                $results[] = $this->create($args, $generation_definitions);
            }

            return $results;
        }

        function generate_args($args = array(), $generation_definitions = null, &$callbacks = null)
        {
            $callbacks = array();
            if (is_null($generation_definitions)) {
                $generation_definitions = $this->default_generation_definitions;
            }

            // Use the same incrementor for all fields belonging to this object.
            $gen = new WP_UnitTest_Generator_Sequence();
            $incr = $gen->get_incr();

            foreach (array_keys($generation_definitions) as $field_name) {
                if (!isset($args[$field_name])) {
                    $generator = $generation_definitions[$field_name];
                    if (is_scalar($generator)) {
                        $args[$field_name] = $generator;
                    } elseif (is_object($generator) && method_exists($generator, 'call')) {
                        $callbacks[$field_name] = $generator;
                    } elseif (is_object($generator)) {
                        $args[$field_name] = sprintf($generator->get_template_string(), $incr);
                    } else {
                        return new WP_Error('invalid_argument', 'Factory default value should be either a scalar or an generator object.');
                    }
                }
            }

            return $args;
        }

        function apply_callbacks($callbacks, $created)
        {
            $updated_fields = array();
            foreach ($callbacks as $field_name => $generator) {
                $updated_fields[$field_name] = $generator->call($created);
            }

            return $updated_fields;
        }

        function callback($function)
        {
            return new WP_UnitTest_Factory_Callback_After_Create($function);
        }

        function addslashes_deep($value)
        {
            if (is_array($value)) {
                $value = array_map(array($this, 'addslashes_deep'), $value);
            } elseif (is_object($value)) {
                $vars = get_object_vars($value);
                foreach ($vars as $key => $data) {
                    $value->{$key} = $this->addslashes_deep($data);
                }
            } elseif (is_string($value)) {
                $value = addslashes($value);
            }

            return $value;
        }

    }

    class WP_UnitTest_Factory_For_Network extends WP_UnitTest_Factory_For_Thing
    {

        function __construct($factory = null)
        {
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'domain'            => WP_TESTS_DOMAIN,
                'title'             => new WP_UnitTest_Generator_Sequence('Network %s'),
                'path'              => new WP_UnitTest_Generator_Sequence('/testpath%s/'),
                'network_id'        => new WP_UnitTest_Generator_Sequence('%s', 2),
                'subdomain_install' => false,
            );
        }

        function create_object($args)
        {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            if (!isset($args['user'])) {
                $email = WP_TESTS_EMAIL;
            } else {
                $email = get_userdata($args['user'])->user_email;
            }

            populate_network($args['network_id'], $args['domain'], $email, $args['title'], $args['path'], $args['subdomain_install']);

            return $args['network_id'];
        }

        function update_object($network_id, $fields)
        {
        }

        function get_object_by_id($network_id)
        {
            return get_network($network_id);
        }
    }

    class WP_UnitTest_Factory_For_Post extends WP_UnitTest_Factory_For_Thing
    {

        function __construct($factory = null)
        {
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'post_status'  => 'publish',
                'post_title'   => new WP_UnitTest_Generator_Sequence('Post title %s'),
                'post_content' => new WP_UnitTest_Generator_Sequence('Post content %s'),
                'post_excerpt' => new WP_UnitTest_Generator_Sequence('Post excerpt %s'),
                'post_type'    => 'post',
            );
        }

        function create_object($args)
        {
            return wp_insert_post($args);
        }

        function update_object($post_id, $fields)
        {
            $fields['ID'] = $post_id;

            return wp_update_post($fields);
        }

        function get_object_by_id($post_id)
        {
            return get_post($post_id);
        }
    }

    class WP_UnitTest_Factory_For_Term extends WP_UnitTest_Factory_For_Thing
    {

        private $taxonomy;
        const DEFAULT_TAXONOMY = 'post_tag';

        function __construct($factory = null, $taxonomy = null)
        {
            parent::__construct($factory);
            $this->taxonomy = $taxonomy ? $taxonomy : self::DEFAULT_TAXONOMY;
            $this->default_generation_definitions = array(
                'name'        => new WP_UnitTest_Generator_Sequence('Term %s'),
                'taxonomy'    => $this->taxonomy,
                'description' => new WP_UnitTest_Generator_Sequence('Term description %s'),
            );
        }

        function create_object($args)
        {
            $args = array_merge(array('taxonomy' => $this->taxonomy), $args);
            $term_id_pair = wp_insert_term($args['name'], $args['taxonomy'], $args);
            if (is_wp_error($term_id_pair)) {
                return $term_id_pair;
            }

            return $term_id_pair['term_id'];
        }

        function update_object($term, $fields)
        {
            $fields = array_merge(array('taxonomy' => $this->taxonomy), $fields);
            if (is_object($term)) {
                $taxonomy = $term->taxonomy;
            }
            $term_id_pair = wp_update_term($term, $taxonomy, $fields);

            return $term_id_pair['term_id'];
        }

        function add_post_terms($post_id, $terms, $taxonomy, $append = true)
        {
            return wp_set_post_terms($post_id, $terms, $taxonomy, $append);
        }

        /**
         * @return array|null|WP_Error|WP_Term
         */
        function create_and_get($args = array(), $generation_definitions = null)
        {
            $term_id = $this->create($args, $generation_definitions);
            $taxonomy = isset($args['taxonomy']) ? $args['taxonomy'] : $this->taxonomy;

            return get_term($term_id, $taxonomy);
        }

        function get_object_by_id($term_id)
        {
            return get_term($term_id, $this->taxonomy);
        }
    }

    class WP_UnitTest_Factory_For_User extends WP_UnitTest_Factory_For_Thing
    {

        function __construct($factory = null)
        {
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'user_login' => new WP_UnitTest_Generator_Sequence('User %s'),
                'user_pass'  => 'password',
                'user_email' => new WP_UnitTest_Generator_Sequence('user_%s@example.org'),
            );
        }

        function create_object($args)
        {
            return wp_insert_user($args);
        }

        function update_object($user_id, $fields)
        {
            $fields['ID'] = $user_id;

            return wp_update_user($fields);
        }

        function get_object_by_id($user_id)
        {
            return new WP_User($user_id);
        }
    }

    class WP_UnitTest_Factory_For_Blog extends WP_UnitTest_Factory_For_Thing
    {

        function __construct($factory = null)
        {
            global $current_site, $base;
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'domain'  => $current_site->domain,
                'path'    => new WP_UnitTest_Generator_Sequence($base . 'testpath%s'),
                'title'   => new WP_UnitTest_Generator_Sequence('Site %s'),
                'site_id' => $current_site->id,
            );
        }

        function create_object($args)
        {
            global $wpdb;
            $meta = isset($args['meta']) ? $args['meta'] : array('public' => 1);
            $user_id = isset($args['user_id']) ? $args['user_id'] : get_current_user_id();
            // temp tables will trigger db errors when we attempt to reference them as new temp tables
            $suppress = $wpdb->suppress_errors();
            $blog = wpmu_create_blog($args['domain'], $args['path'], $args['title'], $user_id, $meta, $args['site_id']);
            $wpdb->suppress_errors($suppress);

            // Tell WP we're done installing.
            wp_installing(false);

            return $blog;
        }

        function update_object($blog_id, $fields)
        {
        }

        function get_object_by_id($blog_id)
        {
            return get_site($blog_id);
        }
    }

    class WP_UnitTest_Factory_For_Bookmark extends WP_UnitTest_Factory_For_Thing
    {

        public function __construct($factory = null)
        {
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'link_name' => new WP_UnitTest_Generator_Sequence('Bookmark name %s'),
                'link_url'  => new WP_UnitTest_Generator_Sequence('Bookmark URL %s'),
            );
        }

        public function create_object($args)
        {
            return wp_insert_link($args);
        }

        public function update_object($link_id, $fields)
        {
            $fields['link_id'] = $link_id;

            return wp_update_link($fields);
        }

        public function get_object_by_id($link_id)
        {
            return get_bookmark($link_id);
        }
    }

    class WP_UnitTest_Factory_For_Comment extends WP_UnitTest_Factory_For_Thing
    {

        function __construct($factory = null)
        {
            parent::__construct($factory);
            $this->default_generation_definitions = array(
                'comment_author'     => new WP_UnitTest_Generator_Sequence('Commenter %s'),
                'comment_author_url' => new WP_UnitTest_Generator_Sequence('http://example.com/%s/'),
                'comment_approved'   => 1,
                'comment_content'    => 'This is a comment',
            );
        }

        function create_object($args)
        {
            return wp_insert_comment($this->addslashes_deep($args));
        }

        function update_object($comment_id, $fields)
        {
            $fields['comment_ID'] = $comment_id;

            return wp_update_comment($this->addslashes_deep($fields));
        }

        function create_post_comments($post_id, $count = 1, $args = array(), $generation_definitions = null)
        {
            $args['comment_post_ID'] = $post_id;

            return $this->create_many($count, $args, $generation_definitions);
        }

        function get_object_by_id($comment_id)
        {
            return get_comment($comment_id);
        }
    }

    class WP_UnitTest_Factory_For_Attachment extends WP_UnitTest_Factory_For_Post
    {

        /**
         * Create an attachment fixture.
         *
         * @param array $args          {
         *                             Array of arguments. Accepts all arguments that can be passed to
         *                             wp_insert_attachment(), in addition to the following:
         *
         * @type int    $post_parent   ID of the post to which the attachment belongs.
         * @type string $file          Path of the attached file.
         * }
         *
         * @param int   $legacy_parent Deprecated.
         * @param array $legacy_args   Deprecated
         */
        function create_object($args, $legacy_parent = 0, $legacy_args = array())
        {
            // Backward compatibility for legacy argument format.
            if (is_string($args)) {
                $file = $args;
                $args = $legacy_args;
                $args['post_parent'] = $legacy_parent;
                $args['file'] = $file;
            }

            $r = array_merge(array(
                                 'file'        => '',
                                 'post_parent' => 0,
                             ), $args);

            return wp_insert_attachment($r, $r['file'], $r['post_parent']);
        }

        function create_upload_object($file, $parent = 0)
        {
            $contents = file_get_contents($file);
            $upload = wp_upload_bits(basename($file), null, $contents);

            $type = '';
            if (!empty($upload['type'])) {
                $type = $upload['type'];
            } else {
                $mime = wp_check_filetype($upload['file']);
                if ($mime) {
                    $type = $mime['type'];
                }
            }

            $attachment = array(
                'post_title'     => basename($upload['file']),
                'post_content'   => '',
                'post_type'      => 'attachment',
                'post_parent'    => $parent,
                'post_mime_type' => $type,
                'guid'           => $upload['url'],
            );

            // Save the data
            $id = wp_insert_attachment($attachment, $upload['file'], $parent);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));

            return $id;
        }
    }

    class WP_UnitTest_Factory
    {

        /**
         * @var WP_UnitTest_Factory_For_Post
         */
        public $post;

        /**
         * @var WP_UnitTest_Factory_For_Attachment
         */
        public $attachment;

        /**
         * @var WP_UnitTest_Factory_For_Comment
         */
        public $comment;

        /**
         * @var WP_UnitTest_Factory_For_User
         */
        public $user;

        /**
         * @var WP_UnitTest_Factory_For_Term
         */
        public $term;

        /**
         * @var WP_UnitTest_Factory_For_Term
         */
        public $category;

        /**
         * @var WP_UnitTest_Factory_For_Term
         */
        public $tag;

        /**
         * @since 4.6.0
         * @var WP_UnitTest_Factory_For_Bookmark
         */
        public $bookmark;

        /**
         * @var WP_UnitTest_Factory_For_Blog
         */
        public $blog;

        /**
         * @var WP_UnitTest_Factory_For_Network
         */
        public $network;

        function __construct()
        {
            $this->post = new WP_UnitTest_Factory_For_Post($this);
            $this->attachment = new WP_UnitTest_Factory_For_Attachment($this);
            $this->comment = new WP_UnitTest_Factory_For_Comment($this);
            $this->user = new WP_UnitTest_Factory_For_User($this);
            $this->term = new WP_UnitTest_Factory_For_Term($this);
            $this->category = new WP_UnitTest_Factory_For_Term($this, 'category');
            $this->tag = new WP_UnitTest_Factory_For_Term($this, 'post_tag');
            $this->bookmark = new WP_UnitTest_Factory_For_Bookmark($this);
            if (is_multisite()) {
                $this->blog = new WP_UnitTest_Factory_For_Blog($this);
                $this->network = new WP_UnitTest_Factory_For_Network($this);
            }
        }
    }

    class WP_UnitTest_Factory_Callback_After_Create
    {
        var $callback;

        function __construct($callback)
        {
            $this->callback = $callback;
        }

        function call($object)
        {
            return call_user_func($this->callback, $object);
        }
    }

    class WP_UnitTest_Generator_Sequence
    {
        static $incr = -1;
        public $next;
        public $template_string;

        function __construct($template_string = '%s', $start = null)
        {
            if ($start) {
                $this->next = $start;
            } else {
                self::$incr++;
                $this->next = self::$incr;
            }
            $this->template_string = $template_string;
        }

        function next()
        {
            $generated = sprintf($this->template_string, $this->next);
            $this->next++;

            return $generated;
        }

        /**
         * Get the incrementor.
         * @since 4.6.0
         * @return int
         */
        public function get_incr()
        {
            return self::$incr;
        }

        /**
         * Get the template string.
         * @since 4.6.0
         * @return string
         */
        public function get_template_string()
        {
            return $this->template_string;
        }
    }
}

namespace Smartling\Tests\IntegrationTests {
    /**
     * Defines a basic fixture to run multiple tests.
     * Resets the state of the WordPress installation before and after every test.
     * Includes utility functions and assertions useful for testing WordPress.
     * All WordPress unit tests should inherit from this class.
     */
    class WP_UnitTestCase extends \PHPUnit_Framework_TestCase
    {

        protected static $hooks_saved = array();
        protected static $ignore_files;

        function __isset($name)
        {
            return 'factory' === $name;
        }

        function __get($name)
        {
            if ('factory' === $name) {
                return self::factory();
            }
        }

        /**
         * Fetches the factory object for generating WordPress fixtures.
         * @return WP_UnitTest_Factory The fixture factory.
         */
        protected static function factory()
        {
            static $factory = null;
            if (!$factory) {
                $factory = new \WP_UnitTest_Factory();
            }

            return $factory;
        }

        public static function get_called_class()
        {
            if (function_exists('get_called_class')) {
                return get_called_class();
            }
        }

        public static function setUpBeforeClass()
        {
            global $wpdb;

            $wpdb->suppress_errors = false;
            $wpdb->show_errors = true;
            $wpdb->db_connect();
            ini_set('display_errors', 1);

            parent::setUpBeforeClass();

            $c = self::get_called_class();
            if (!method_exists($c, 'wpSetUpBeforeClass')) {
                return;
            }

            call_user_func(array($c, 'wpSetUpBeforeClass'), self::factory());
        }

        public static function tearDownAfterClass()
        {
            parent::tearDownAfterClass();

            self::flush_cache();

            $c = self::get_called_class();
            if (!method_exists($c, 'wpTearDownAfterClass')) {
                return;
            }

            call_user_func(array($c, 'wpTearDownAfterClass'));
        }

        function setUp()
        {
            set_time_limit(0);

            if (!self::$ignore_files) {
                self::$ignore_files = $this->scan_user_uploads();
            }

            if (!self::$hooks_saved) {
                $this->_backup_hooks();
            }

            global $wp_rewrite;

            $this->clean_up_global_scope();

            /*
             * When running core tests, ensure that post types and taxonomies
             * are reset for each test. We skip this step for non-core tests,
             * given the large number of plugins that register post types and
             * taxonomies at 'init'.
             */

            //$this->reset_post_types();
            //$this->reset_taxonomies();
            //$this->reset_post_statuses();
            $this->reset__SERVER();

            if ($wp_rewrite->permalink_structure) {
                $this->set_permalink_structure('');
            }
            add_filter('wp_die_handler', array($this, 'get_wp_die_handler'));
        }


        /**
         * After a test method runs, reset any state in WordPress the test method might have changed.
         */
        function tearDown()
        {
            global $wpdb, $wp_query, $wp;

            if (\is_multisite()) {
                while (ms_is_switched()) {
                    \restore_current_blog();
                }
            }

            $wp_query = new \WP_Query();
            $wp = new \WP();

            // Reset globals related to the post loop and `setup_postdata()`.
            $post_globals = array('post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages',
                                  'multipage', 'more', 'numpages');
            foreach ($post_globals as $global) {
                $GLOBALS[$global] = null;
            }

            \remove_theme_support('html5');

            \remove_filter('wp_die_handler', array($this, 'get_wp_die_handler'));
            $this->_restore_hooks();
            \wp_set_current_user(0);
        }

        function clean_up_global_scope()
        {
            $_GET = array();
            $_POST = array();
            self::flush_cache();
        }


        /**
         * Unregister existing post types and register defaults.
         * Run before each test in order to clean up the global scope, in case
         * a test forgets to unregister a post type on its own, or fails before
         * it has a chance to do so.
         */
        protected function reset_post_types()
        {
            foreach (\get_post_types(array(), 'objects') as $pt) {
                if (empty($pt->tests_no_auto_unregister)) {
                    \_unregister_post_type($pt->name);
                }
            }
            \create_initial_post_types();
        }

        /**
         * Unregister existing taxonomies and register defaults.
         * Run before each test in order to clean up the global scope, in case
         * a test forgets to unregister a taxonomy on its own, or fails before
         * it has a chance to do so.
         */
        protected function reset_taxonomies()
        {
            foreach (\get_taxonomies() as $tax) {
                \_unregister_taxonomy($tax);
            }
            \create_initial_taxonomies();
        }

        /**
         * Unregister non-built-in post statuses.
         */
        protected function reset_post_statuses()
        {
            foreach (\get_post_stati(array('_builtin' => false)) as $post_status) {
                \_unregister_post_status($post_status);
            }
        }

        /**
         * Reset `$_SERVER` variables
         */
        protected function reset__SERVER()
        {
            tests_reset__SERVER();
        }

        /**
         * Saves the action and filter-related globals so they can be restored later.
         * Stores $merged_filters, $wp_actions, $wp_current_filter, and $wp_filter
         * on a class variable so they can be restored on tearDown() using _restore_hooks().
         * @global array $merged_filters
         * @global array $wp_actions
         * @global array $wp_current_filter
         * @global array $wp_filter
         * @return void
         */
        protected function _backup_hooks()
        {
            $globals = array('wp_actions', 'wp_current_filter');
            foreach ($globals as $key) {
                self::$hooks_saved[$key] = $GLOBALS[$key];
            }
            self::$hooks_saved['wp_filter'] = array();
            foreach ($GLOBALS['wp_filter'] as $hook_name => $hook_object) {
                self::$hooks_saved['wp_filter'][$hook_name] = clone $hook_object;
            }
        }

        /**
         * Restores the hook-related globals to their state at setUp()
         * so that future tests aren't affected by hooks set during this last test.
         * @global array $merged_filters
         * @global array $wp_actions
         * @global array $wp_current_filter
         * @global array $wp_filter
         * @return void
         */
        protected function _restore_hooks()
        {
            $globals = array('wp_actions', 'wp_current_filter');
            foreach ($globals as $key) {
                if (isset(self::$hooks_saved[$key])) {
                    $GLOBALS[$key] = self::$hooks_saved[$key];
                }
            }
            if (isset(self::$hooks_saved['wp_filter'])) {
                $GLOBALS['wp_filter'] = array();
                foreach (self::$hooks_saved['wp_filter'] as $hook_name => $hook_object) {
                    $GLOBALS['wp_filter'][$hook_name] = clone $hook_object;
                }
            }
        }

        static function flush_cache()
        {
            global $wp_object_cache;
            $wp_object_cache->group_ops = array();
            $wp_object_cache->stats = array();
            $wp_object_cache->memcache_debug = array();
            $wp_object_cache->cache = array();
            if (method_exists($wp_object_cache, '__remoteset')) {
                $wp_object_cache->__remoteset();
            }
            \wp_cache_flush();
            \wp_cache_add_global_groups(array('users', 'userlogins', 'usermeta', 'user_meta', 'useremail', 'userslugs',
                                             'site-transient', 'site-options', 'blog-lookup', 'blog-details', 'rss',
                                             'global-posts', 'blog-id-cache', 'networks', 'sites', 'site-details'));
            \wp_cache_add_non_persistent_groups(array('comment', 'counts', 'plugins'));
        }


        function get_wp_die_handler($handler)
        {
            return array($this, 'wp_die_handler');
        }

        function wp_die_handler($message)
        {
            if (!is_scalar($message)) {
                $message = '0';
            }

            throw new \WPDieException($message);
        }

        /**
         * Define constants after including files.
         */
        function prepareTemplate(\Text_Template $template)
        {
            $template->setVar(array('constants' => ''));
            $template->setVar(array('wp_constants' => \PHPUnit_Util_GlobalState::getConstantsAsString()));
            parent::prepareTemplate($template);
        }

        /**
         * Check each of the WP_Query is_* functions/properties against expected boolean value.
         * Any properties that are listed by name as parameters will be expected to be true; all others are
         * expected to be false. For example, assertQueryTrue('is_single', 'is_feed') means is_single()
         * and is_feed() must be true and everything else must be false to pass.
         *
         * @param string $prop,... Any number of WP_Query properties that are expected to be true for the current
         *                         request.
         */
        function assertQueryTrue(/* ... */)
        {
            global $wp_query;
            $all = array(
                'is_404',
                'is_admin',
                'is_archive',
                'is_attachment',
                'is_author',
                'is_category',
                'is_comment_feed',
                'is_date',
                'is_day',
                'is_embed',
                'is_feed',
                'is_front_page',
                'is_home',
                'is_month',
                'is_page',
                'is_paged',
                'is_post_type_archive',
                'is_posts_page',
                'is_preview',
                'is_robots',
                'is_search',
                'is_single',
                'is_singular',
                'is_tag',
                'is_tax',
                'is_time',
                'is_trackback',
                'is_year',
            );
            $true = func_get_args();

            foreach ($true as $true_thing) {
                $this->assertContains($true_thing, $all, "Unknown conditional: {$true_thing}.");
            }

            $passed = true;
            $message = '';

            foreach ($all as $query_thing) {
                $result = is_callable($query_thing) ? call_user_func($query_thing) : $wp_query->$query_thing;

                if (in_array($query_thing, $true)) {
                    if (!$result) {
                        $message .= $query_thing . ' is false but is expected to be true. ' . PHP_EOL;
                        $passed = false;
                    }
                } else {
                    if ($result) {
                        $message .= $query_thing . ' is true but is expected to be false. ' . PHP_EOL;
                        $passed = false;
                    }
                }
            }

            if (!$passed) {
                $this->fail($message);
            }
        }

        function remove_added_uploads()
        {
            // Remove all uploads.
            $uploads = wp_upload_dir();
            $this->rmdir($uploads['basedir']);
        }

        function files_in_dir( $dir ) {
            $files = array();

            $iterator = new \RecursiveDirectoryIterator( $dir );
            $objects = new \RecursiveIteratorIterator( $iterator );
            foreach ( $objects as $name => $object ) {
                if ( is_file( $name ) ) {
                    $files[] = $name;
                }
            }

            return $files;
        }

        function scan_user_uploads()
        {
            static $files = array();
            if (!empty($files)) {
                return $files;
            }

            $uploads = wp_upload_dir();
            $files = $this->files_in_dir($uploads['basedir']);

            return $files;
        }

        /**
         * Helper to Convert a microtime string into a float
         */
        protected function _microtime_to_float($microtime)
        {
            $time_array = explode(' ', $microtime);

            return array_sum($time_array);
        }

        /**
         * Multisite-agnostic way to delete a user from the database.
         * @since 4.3.0
         */
        public static function delete_user($user_id)
        {
            if (is_multisite()) {
                return wpmu_delete_user($user_id);
            } else {
                return wp_delete_user($user_id);
            }
        }

        /**
         * Utility method that resets permalinks and flushes rewrites.
         * @since 4.4.0
         * @global WP_Rewrite $wp_rewrite
         *
         * @param string      $structure Optional. Permalink structure to set. Default empty.
         */
        public function set_permalink_structure($structure = '')
        {
            global $wp_rewrite;

            $wp_rewrite->init();
            $wp_rewrite->set_permalink_structure($structure);
            $wp_rewrite->flush_rules();
        }

        function _make_attachment($upload, $parent_post_id = 0)
        {
            $type = '';
            if (!empty($upload['type'])) {
                $type = $upload['type'];
            } else {
                $mime = wp_check_filetype($upload['file']);
                if ($mime) {
                    $type = $mime['type'];
                }
            }

            $attachment = array(
                'post_title'     => basename($upload['file']),
                'post_content'   => '',
                'post_type'      => 'attachment',
                'post_parent'    => $parent_post_id,
                'post_mime_type' => $type,
                'guid'           => $upload['url'],
            );

            // Save the data
            $id = wp_insert_attachment($attachment, $upload['file'], $parent_post_id);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));

            return $id;
        }

        /**
         * There's no way to change post_modified through WP functions.
         */
        protected function update_post_modified($post_id, $date)
        {
            global $wpdb;

            return $wpdb->update(
                $wpdb->posts,
                array(
                    'post_modified'     => $date,
                    'post_modified_gmt' => $date,
                ),
                array(
                    'ID' => $post_id,
                ),
                array(
                    '%s',
                    '%s',
                ),
                array(
                    '%d',
                )
            );
        }
    }
}
