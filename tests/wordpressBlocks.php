<?php

if (!function_exists('get_comment_delimited_block_content')) {
    /**
     * @param string|null $block_name Block name.
     * @param array $block_attributes Block attributes.
     * @param string $block_content Block save content.
     * @return string Comment-delimited block content.
     */
    function get_comment_delimited_block_content($block_name, $block_attributes, $block_content)
    {
        if (is_null($block_name)) {
            return $block_content;
        }

        $serialized_block_name = strip_core_block_namespace($block_name);
        $serialized_attributes = empty($block_attributes) ? '' : serialize_block_attributes($block_attributes) . ' ';

        if (empty($block_content)) {
            return sprintf('<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes);
        }

        return sprintf(
            '<!-- wp:%s %s-->%s<!-- /wp:%s -->',
            $serialized_block_name,
            $serialized_attributes,
            $block_content,
            $serialized_block_name
        );
    }
}

if (!function_exists('parse_blocks')) {
    /**
     * @param string $content
     * @return WP_Block_Parser_Block[]
     */
    function parse_blocks($content)
    {
        return (new WP_Block_Parser())->parse($content);
    }
}

if (!function_exists('serialize_block')) {
    /**
     * @param WP_Block_Parser_Block|array $block A single parsed block object.
     * @return string String of rendered HTML.
     */
    function serialize_block($block)
    {
        $block_content = '';

        $index = 0;
        foreach ($block['innerContent'] as $chunk) {
            $block_content .= is_string($chunk) ? $chunk : serialize_block($block['innerBlocks'][$index++]);
        }

        if (!is_array($block['attrs'])) {
            $block['attrs'] = [];
        }

        return get_comment_delimited_block_content(
            $block['blockName'],
            $block['attrs'],
            $block_content
        );
    }
}

if (!function_exists('serialize_block_attributes')) {
    /**
     * @param array $block_attributes Attributes object.
     * @return string Serialized attributes.
     */
    function serialize_block_attributes($block_attributes)
    {
        $encoded_attributes = json_encode($block_attributes);
        $encoded_attributes = str_replace("--", '\\u002d\\u002d', $encoded_attributes);
        $encoded_attributes = preg_replace('/</', '\\u003c', $encoded_attributes);
        $encoded_attributes = preg_replace('/>/', '\\u003e', $encoded_attributes);
        $encoded_attributes = preg_replace('/&/', '\\u0026', $encoded_attributes);
        $encoded_attributes = preg_replace('/\\\\"/', '\\u0022', $encoded_attributes);

        return $encoded_attributes;
    }
}

if (!function_exists('strip_core_block_namespace')) {
    /**
     * @param string $block_name Original block name.
     * @return string Block name to use for serialization.
     */
    function strip_core_block_namespace($block_name = null)
    {
        if (is_string($block_name) && 0 === strpos($block_name, 'core/')) {
            return substr($block_name, 5);
        }
        return $block_name;
    }
}

if (!class_exists(WP_Block_Parser_Block::class)) {
    class WP_Block_Parser_Block
    {
        /**
         * @var string
         */
        public $blockName;

        /**
         * @var array|null
         */
        public $attrs;

        /**
         * @var WP_Block_Parser_Block[]
         */
        public $innerBlocks;

        /**
         * @var string
         */
        public $innerHTML;

        /**
         * @var array
         */
        public $innerContent;

        /**
         * @param string $name Name of block.
         * @param array $attrs Optional set of attributes from block comment delimiters.
         * @param array $innerBlocks List of inner blocks (of this same class).
         * @param string $innerHTML Resultant HTML from inside block comment delimiters after removing inner blocks.
         * @param array $innerContent List of string fragments and null markers where inner blocks were found.
         */
        public function __construct($name, $attrs, $innerBlocks, $innerHTML, $innerContent)
        {
            $this->blockName = $name;
            $this->attrs = $attrs;
            $this->innerBlocks = $innerBlocks;
            $this->innerHTML = $innerHTML;
            $this->innerContent = $innerContent;
        }
    }
}

if (!class_exists(WP_Block_Parser_Frame::class)) {
    class WP_Block_Parser_Frame
    {
        /**
         * @var WP_Block_Parser_Block
         */
        public $block;

        /**
         * @var int
         */
        public $token_start;

        /**
         * @var int
         */
        public $token_length;

        /**
         * @var int
         */
        public $prev_offset;

        /**
         * @var int
         */
        public $leading_html_start;

        /**
         * @param WP_Block_Parser_Block $block Full or partial block.
         * @param int $token_start Byte offset into document for start of parse token.
         * @param int $token_length Byte length of entire parse token string.
         * @param int $prev_offset Byte offset into document for after parse token ends.
         * @param int $leading_html_start Byte offset into document where leading HTML before token starts.
         */
        public function __construct($block, $token_start, $token_length, $prev_offset = null, $leading_html_start = null)
        {
            $this->block = $block;
            $this->token_start = $token_start;
            $this->token_length = $token_length;
            $this->prev_offset = isset($prev_offset) ? $prev_offset : $token_start + $token_length;
            $this->leading_html_start = $leading_html_start;
        }
    }
}

if (!class_exists(WP_Block_Parser::class)) {
    class WP_Block_Parser
    {
        /**
         * @var string
         */
        public $document;

        /**
         * @var int
         */
        public $offset;

        /**
         * @var WP_Block_Parser_Block[]
         */
        public $output;

        /**
         * @var WP_Block_Parser_Frame[]
         */
        public $stack;

        /**
         * @var array empty associative array
         */
        public $empty_attrs;

        /**
         * @param string $document Input document being parsed.
         * @return WP_Block_Parser_Block[]
         */
        public function parse($document)
        {
            $this->document = $document;
            $this->offset = 0;
            $this->output = array();
            $this->stack = array();
            $this->empty_attrs = json_decode('{}', true);

            do {
            } while ($this->proceed());

            return $this->output;
        }

        /**
         * @return bool
         */
        private function proceed()
        {
            list($token_type, $block_name, $attrs, $start_offset, $token_length) = $this->next_token();
            $stack_depth = count($this->stack);

            $leading_html_start = $start_offset > $this->offset ? $this->offset : null;

            switch ($token_type) {
                case 'no-more-tokens':
                    if (0 === $stack_depth) {
                        $this->add_freeform();
                        return false;
                    }

                    if (1 === $stack_depth) {
                        $this->add_block_from_stack();
                        return false;
                    }

                    while (0 < count($this->stack)) {
                        $this->add_block_from_stack();
                    }
                    return false;

                case 'void-block':
                    if (0 === $stack_depth) {
                        if (isset($leading_html_start)) {
                            $this->output[] = (array)$this->freeform(
                                substr(
                                    $this->document,
                                    $leading_html_start,
                                    $start_offset - $leading_html_start
                                )
                            );
                        }

                        $this->output[] = (array)new WP_Block_Parser_Block($block_name, $attrs, array(), '', array());
                        $this->offset = $start_offset + $token_length;
                        return true;
                    }

                    $this->add_inner_block(
                        new WP_Block_Parser_Block($block_name, $attrs, array(), '', array()),
                        $start_offset,
                        $token_length
                    );
                    $this->offset = $start_offset + $token_length;
                    return true;

                case 'block-opener':
                    $this->stack[] = new WP_Block_Parser_Frame(
                        new WP_Block_Parser_Block($block_name, $attrs, array(), '', array()),
                        $start_offset,
                        $token_length,
                        $start_offset + $token_length,
                        $leading_html_start
                    );
                    $this->offset = $start_offset + $token_length;
                    return true;

                case 'block-closer':
                    if (0 === $stack_depth) {
                        $this->add_freeform();
                        return false;
                    }

                    if (1 === $stack_depth) {
                        $this->add_block_from_stack($start_offset);
                        $this->offset = $start_offset + $token_length;
                        return true;
                    }

                    $stack_top = array_pop($this->stack);
                    $html = substr($this->document, $stack_top->prev_offset, $start_offset - $stack_top->prev_offset);
                    $stack_top->block->innerHTML .= $html;
                    $stack_top->block->innerContent[] = $html;
                    $stack_top->prev_offset = $start_offset + $token_length;

                    $this->add_inner_block(
                        $stack_top->block,
                        $stack_top->token_start,
                        $stack_top->token_length,
                        $start_offset + $token_length
                    );
                    $this->offset = $start_offset + $token_length;
                    return true;

                default:
                    $this->add_freeform();
                    return false;
            }
        }

        /**
         * @return array
         */
        private function next_token()
        {
            $matches = null;

            $has_match = preg_match(
                '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s',
                $this->document,
                $matches,
                PREG_OFFSET_CAPTURE,
                $this->offset
            );

            if (false === $has_match) {
                return array('no-more-tokens', null, null, null, null);
            }

            if (0 === $has_match) {
                return array('no-more-tokens', null, null, null, null);
            }

            list($match, $started_at) = $matches[0];

            $length = strlen($match);
            $is_closer = isset($matches['closer']) && -1 !== $matches['closer'][1];
            $is_void = isset($matches['void']) && -1 !== $matches['void'][1];
            $namespace = $matches['namespace'];
            $namespace = (isset($namespace) && -1 !== $namespace[1]) ? $namespace[0] : 'core/';
            $name = $namespace . $matches['name'][0];
            $has_attrs = isset($matches['attrs']) && -1 !== $matches['attrs'][1];

            $attrs = $has_attrs
                ? json_decode($matches['attrs'][0], true)
                : $this->empty_attrs;

            if ($is_closer && ($is_void || $has_attrs)) {
                // ignore
            }

            if ($is_void) {
                return array('void-block', $name, $attrs, $started_at, $length);
            }

            if ($is_closer) {
                return array('block-closer', $name, null, $started_at, $length);
            }

            return array('block-opener', $name, $attrs, $started_at, $length);
        }

        /**
         * @param string $innerHTML HTML content of block.
         * @return WP_Block_Parser_Block freeform block object.
         */
        private function freeform($innerHTML)
        {
            return new WP_Block_Parser_Block(null, $this->empty_attrs, array(), $innerHTML, array($innerHTML));
        }

        /**
         * @param int|null $length how many bytes of document text to output.
         */
        private function add_freeform($length = null)
        {
            $length = $length ?: strlen($this->document) - $this->offset;

            if (0 === $length) {
                return;
            }

            $this->output[] = (array)$this->freeform(substr($this->document, $this->offset, $length));
        }

        /**
         * @param WP_Block_Parser_Block $block The block to add to the output.
         * @param int $token_start Byte offset into the document where the first token for the block starts.
         * @param int $token_length Byte length of entire block from start of opening token to end of closing token.
         * @param int|null $last_offset Last byte offset into document if continuing form earlier output.
         */
        private function add_inner_block(WP_Block_Parser_Block $block, $token_start, $token_length, $last_offset = null)
        {
            $parent = $this->stack[count($this->stack) - 1];
            $parent->block->innerBlocks[] = (array)$block;
            $html = substr($this->document, $parent->prev_offset, $token_start - $parent->prev_offset);

            if (!empty($html)) {
                $parent->block->innerHTML .= $html;
                $parent->block->innerContent[] = $html;
            }

            $parent->block->innerContent[] = null;
            $parent->prev_offset = $last_offset ?: $token_start + $token_length;
        }

        /**
         * @param int|null $end_offset byte offset into document for where we should stop sending text output as HTML.
         */
        private function add_block_from_stack($end_offset = null)
        {
            $stack_top = array_pop($this->stack);
            $prev_offset = $stack_top->prev_offset;

            $html = isset($end_offset)
                ? substr($this->document, $prev_offset, $end_offset - $prev_offset)
                : substr($this->document, $prev_offset);

            if (!empty($html)) {
                $stack_top->block->innerHTML .= $html;
                $stack_top->block->innerContent[] = $html;
            }

            if (isset($stack_top->leading_html_start)) {
                $this->output[] = (array)$this->freeform(
                    substr(
                        $this->document,
                        $stack_top->leading_html_start,
                        $stack_top->token_start - $stack_top->leading_html_start
                    )
                );
            }

            $this->output[] = (array)$stack_top->block;
        }
    }
}

if (!class_exists(WP_Block_Type::class)) {
    class WP_Block_Type
    {
        public $name;
        public $render_callback;
        public $attributes;
        public $editor_script;
        public $script;
        public $editor_style;
        public $style;

        /**
         * @param string $block_type
         * @param array|string $args
         */
        public function __construct($block_type, $args = array())
        {
            $this->name = $block_type;

            $this->set_props($args);
        }

        /**
         * @param array $attributes
         * @param string $content
         * @return string
         */
        public function render($attributes = array(), $content = '')
        {
            if (!$this->is_dynamic()) {
                return '';
            }

            $attributes = $this->prepare_attributes_for_render($attributes);

            return (string)call_user_func($this->render_callback, $attributes, $content);
        }

        /**
         * @return boolean
         */
        public function is_dynamic()
        {
            return is_callable($this->render_callback);
        }

        /**
         * @param array $attributes
         * @return array
         */
        public function prepare_attributes_for_render($attributes)
        {
            if (!isset($this->attributes)) {
                return $attributes;
            }

            foreach ($attributes as $attribute_name => $value) {
                if (!isset($this->attributes[$attribute_name])) {
                    continue;
                }

                $schema = $this->attributes[$attribute_name];
                $is_valid = rest_validate_value_from_schema($value, $schema);
                if (is_wp_error($is_valid)) {
                    unset($attributes[$attribute_name]);
                }
            }

            $missing_schema_attributes = array_diff_key($this->attributes, $attributes);
            foreach ($missing_schema_attributes as $attribute_name => $schema) {
                if (isset($schema['default'])) {
                    $attributes[$attribute_name] = $schema['default'];
                }
            }

            return $attributes;
        }

        /**
         * @param array|string $args
         */
        public function set_props($args)
        {
            $args = wp_parse_args($args,
                [
                    'render_callback' => null,
                ]
            );

            $args['name'] = $this->name;

            foreach ($args as $property_name => $property_value) {
                $this->$property_name = $property_value;
            }
        }

        /**
         * @return array
         */
        public function get_attributes()
        {
            return is_array($this->attributes) ?
                array_merge(
                    $this->attributes,
                    [
                        'layout' => [
                            'type' => 'string',
                        ],
                    ]
                ) :
                [
                    'layout' => [
                        'type' => 'string',
                    ],
                ];
        }
    }
}

if (!class_exists(WP_Block_Type_Registry::class)) {
    class WP_Block_Type_Registry
    {
        private $registered_block_types = [];
        private static $instance = null;

        /**
         * @param string|WP_Block_Type $name
         * @param array $args
         * @return WP_Block_Type|false
         */
        public function register($name, array $args = [])
        {
            $block_type = null;
            if ($name instanceof WP_Block_Type) {
                $block_type = $name;
                $name = $block_type->name;
            }

            if (!is_string($name)) {
                throw new RuntimeException('Block type names must be strings.');
            }

            if (preg_match('/[A-Z]+/', $name)) {
                throw new RuntimeException('Block type names must not contain uppercase characters.');
            }

            $name_matcher = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
            if (!preg_match($name_matcher, $name)) {
                throw new RuntimeException('Block type names must contain a namespace prefix. Example: my-plugin/my-custom-block-type');
            }

            if ($this->is_registered($name)) {
                throw new \RuntimeException(sprintf('Block type "%s" is already registered.', $name));
            }

            if (!$block_type) {
                $block_type = new WP_Block_Type($name, $args);
            }

            $this->registered_block_types[$name] = $block_type;

            return $block_type;
        }

        /**
         * @param string|WP_Block_Type $name
         * @return WP_Block_Type|false
         */
        public function unregister($name)
        {
            if ($name instanceof WP_Block_Type) {
                $name = $name->name;
            }

            if (!$this->is_registered($name)) {
                throw new RuntimeException(sprintf('Block type "%s" is not registered.', $name));
            }

            $unregistered_block_type = $this->registered_block_types[$name];
            unset($this->registered_block_types[$name]);

            return $unregistered_block_type;
        }

        /**
         * @param string $name
         * @return WP_Block_Type|null
         */
        public function get_registered($name)
        {
            if (!$this->is_registered($name)) {
                return null;
            }

            return $this->registered_block_types[$name];
        }

        /**
         * @return WP_Block_Type[]
         */
        public function get_all_registered()
        {
            return $this->registered_block_types;
        }

        /**
         * @param string $name
         * @return bool
         */
        public function is_registered($name)
        {
            return isset($this->registered_block_types[$name]);
        }

        /**
         * @return WP_Block_Type_Registry
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }
    }
}
