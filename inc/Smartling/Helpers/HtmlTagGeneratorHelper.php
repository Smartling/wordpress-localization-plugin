<?php

namespace Smartling\Helpers;


class HtmlTagGeneratorHelper
{
    /**
     * @var array list of void elements (element name => 1)
     * @see http://www.w3.org/TR/html-markup/syntax.html#void-element
     */
    public static $voidElements = [
        'area'    => 1,
        'base'    => 1,
        'br'      => 1,
        'col'     => 1,
        'command' => 1,
        'embed'   => 1,
        'hr'      => 1,
        'img'     => 1,
        'input'   => 1,
        'keygen'  => 1,
        'link'    => 1,
        'meta'    => 1,
        'param'   => 1,
        'source'  => 1,
        'track'   => 1,
        'wbr'     => 1,
    ];

    /**
     * @var array the preferred order of attributes in a tag. This mainly affects the order of the attributes
     * that are rendered by [[renderTagAttributes()]].
     */
    public static $attributeOrder = [
        'type',
        'id',
        'class',
        'name',
        'value',
        'href',
        'src',
        'action',
        'method',
        'selected',
        'checked',
        'readonly',
        'disabled',
        'multiple',
        'size',
        'maxlength',
        'width',
        'height',
        'rows',
        'cols',
        'alt',
        'title',
        'rel',
        'media',
    ];

    /**
     * Decodes special HTML entities back to the corresponding characters.
     * This is the opposite of [[encode()]].
     *
     * @param string $content the content to be decoded
     *
     * @return string the decoded content
     * @see encode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars-decode.php
     */
    public static function decode($content)
    {
        return htmlspecialchars_decode($content, ENT_QUOTES);
    }

    /**
     * Generates a start tag.
     *
     * @param string $name    the tag name
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated start tag
     * @see endTag()
     * @see tag()
     */
    public static function beginTag($name, $options = [])
    {
        return "<$name" . static::renderTagAttributes($options) . '>';
    }

    /**
     * Renders the HTML tag attributes.
     *
     * Attributes whose values are of boolean type will be treated as
     * [boolean attributes](http://www.w3.org/TR/html5/infrastructure.html#boolean-attributes).
     *
     * Attributes whose values are null will not be rendered.
     *
     * The values of attributes will be HTML-encoded using [[encode()]].
     *
     * The "data" attribute is specially handled when it is receiving an array value. In this case,
     * the array will be "expanded" and a list data attributes will be rendered. For example,
     * if `'data' => ['id' => 1, 'name' => 'yii']`, then this will be rendered:
     * `data-id="1" data-name="yii"`.
     * Additionally `'data' => ['params' => ['id' => 1, 'name' => 'yii'], 'status' => 'ok']` will be rendered as:
     * `data-params='{"id":1,"name":"yii"}' data-status="ok"`.
     *
     * @param array $attributes attributes to be rendered. The attribute values will be HTML-encoded using [[encode()]].
     *
     * @return string the rendering result. If the attributes are not empty, they will be rendered
     * into a string with a leading white space (so that it can be directly appended to the tag name
     * in a tag. If there is no attribute, an empty string will be returned.
     */
    public static function renderTagAttributes($attributes)
    {
        if (count($attributes) > 1) {
            $sorted = [];
            foreach (static::$attributeOrder as $name) {
                if (isset($attributes[$name])) {
                    $sorted[$name] = $attributes[$name];
                }
            }
            $attributes = array_merge($sorted, $attributes);
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html .= " $name";
                }
            } elseif (is_array($value) && $name === 'data') {
                foreach ($value as $n => $v) {
                    if (is_array($v)) {
                        $html .= " $name-$n='" . json_encode($v, JSON_HEX_APOS) . "'";
                    } else {
                        $html .= " $name-$n=\"" . static::encode($v) . '"';
                    }
                }
            } elseif ($value !== null) {
                $html .= " $name=\"" . static::encode($value) . '"';
            }
        }

        return $html;
    }

    /**
     * Encodes special characters into HTML entities.
     * The UTF-8 charset will be used for encoding.
     *
     * @param string  $content      the content to be encoded
     * @param boolean $doubleEncode whether to encode HTML entities in `$content`. If false,
     *                              HTML entities in `$content` will not be further encoded.
     *
     * @return string the encoded content
     * @see decode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars.php
     */
    public static function encode($content, $doubleEncode = true)
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }

    /**
     * Generates an end tag.
     *
     * @param string $name the tag name
     *
     * @return string the generated end tag
     * @see beginTag()
     * @see tag()
     */
    public static function endTag($name)
    {
        return "</$name>";
    }


    /**
     * Generates a mailto hyperlink.
     *
     * @param string $text    link body. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     *                        such as an image tag. If this is coming from end users, you should consider [[encode()]]
     *                        it to prevent XSS attacks.
     * @param string $email   email address. If this is null, the first parameter (link body) will be treated
     *                        as the email address and used.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated mailto link
     */
    public static function mailto($text, $email = null, $options = [])
    {
        $options['href'] = 'mailto:' . ($email === null ? $text : $email);

        return static::tag('a', $text, $options);
    }

    /**
     * Generates a complete HTML tag.
     *
     * @param string $name    the tag name
     * @param string $content the content to be enclosed between the start and end tags. It will not be HTML-encoded.
     *                        If this is coming from end users, you should consider [[encode()]] it to prevent XSS
     *                        attacks.
     * @param array  $options the HTML tag attributes (HTML options) in terms of name-value pairs.
     *                        These will be rendered as the attributes of the resulting tag. The values will be
     *                        HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not
     *                        be rendered.
     *
     * For example when using `['class' => 'my-class', 'target' => '_blank', 'value' => null]` it will result in the
     * html attributes rendered like this: `class="my-class" target="_blank"`.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated HTML tag
     * @see beginTag()
     * @see endTag()
     */
    public static function tag($name, $content = '', $options = [])
    {
        $html = "<$name" . static::renderTagAttributes($options) . '>';

        return isset(static::$voidElements[strtolower($name)]) ? $html : "$html$content</$name>";
    }

    /**
     * Generates a submit button tag.
     *
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     *                        Therefore you can pass in HTML code such as an image tag. If this is is coming from end
     *                        users, you should consider [[encode()]] it to prevent XSS attacks.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated submit button tag
     */
    public static function submitButton($content = 'Submit', $options = [])
    {
        $options['type'] = 'submit';

        return static::button($content, $options);
    }

    /**
     * Generates a button tag.
     *
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     *                        Therefore you can pass in HTML code such as an image tag. If this is is coming from end
     *                        users, you should consider [[encode()]] it to prevent XSS attacks.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated button tag
     */
    public static function button($content = 'Button', $options = [])
    {
        if (!isset($options['type'])) {
            $options['type'] = 'button';
        }

        return static::tag('button', $content, $options);
    }

    /**
     * Generates a reset button tag.
     *
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     *                        Therefore you can pass in HTML code such as an image tag. If this is is coming from end
     *                        users, you should consider [[encode()]] it to prevent XSS attacks.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated reset button tag
     */
    public static function resetButton($content = 'Reset', $options = [])
    {
        $options['type'] = 'reset';

        return static::button($content, $options);
    }

    /**
     * Generates an input button.
     *
     * @param string $label   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated button tag
     */
    public static function buttonInput($label = 'Button', $options = [])
    {
        $options['type'] = 'button';
        $options['value'] = $label;

        return static::tag('input', '', $options);
    }

    /**
     * Generates a submit input button.
     *
     * @param string $label   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated button tag
     */
    public static function submitInput($label = 'Submit', $options = [])
    {
        $options['type'] = 'submit';
        $options['value'] = $label;

        return static::tag('input', '', $options);
    }

    /**
     * Generates a reset input button.
     *
     * @param string $label   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the attributes of the button tag. The values will be HTML-encoded using [[encode()]].
     *                        Attributes whose value is null will be ignored and not put in the tag returned.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated button tag
     */
    public static function resetInput($label = 'Reset', $options = [])
    {
        $options['type'] = 'reset';
        $options['value'] = $label;

        return static::tag('input', '', $options);
    }

    /**
     * Generates a text input field.
     *
     * @param string $name    the name attribute.
     * @param string $value   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated text input tag
     */
    public static function textInput($name, $value = null, $options = [])
    {
        return static::input('text', $name, $value, $options);
    }

    /**
     * Generates an input type of the given type.
     *
     * @param string $type    the type attribute.
     * @param string $name    the name attribute. If it is null, the name attribute will not be generated.
     * @param string $value   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated input tag
     */
    public static function input($type, $name = null, $value = null, $options = [])
    {
        $options['type'] = $type;
        $options['name'] = $name;
        $options['value'] = $value === null ? null : (string)$value;

        return static::tag('input', '', $options);
    }

    /**
     * Generates a password input field.
     *
     * @param string $name    the name attribute.
     * @param string $value   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated password input tag
     */
    public static function passwordInput($name, $value = null, $options = [])
    {
        return static::input('password', $name, $value, $options);
    }

    /**
     * Generates a text area input.
     *
     * @param string $name    the input name
     * @param string $value   the input value. Note that it will be encoded using [[encode()]].
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated text area tag
     */
    public static function textarea($name, $value = '', $options = [])
    {
        $options['name'] = $name;

        return static::tag('textarea', static::encode($value), $options);
    }

    /**
     * Generates a drop-down list.
     *
     * @param string $name      the input name
     * @param string $selection the selected value
     * @param array  $items     the option data items. The array keys are option values, and the array values
     *                          are the corresponding option labels. The array can also be nested (i.e. some array
     *                          values are arrays too). For each sub-array, an option group will be generated whose
     *                          label is the key associated with the sub-array. If you have a list of data models, you
     *                          may convert them into the format described above using
     *                          [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array  $options   the tag options in terms of name-value pairs. The following options are specially
     *                          handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option;
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ~~~
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ~~~
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to `false`.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated drop-down list tag
     */
    public static function dropDownList($name, $selection = null, $items = [], $options = [])
    {
        if (!empty($options['multiple'])) {
            return static::listBox($name, $selection, $items, $options);
        }
        $options['name'] = $name;
        unset($options['unselect']);
        $selectOptions = static::renderSelectOptions($selection, $items, $options);

        return static::tag('select', "\n" . $selectOptions . "\n", $options);
    }

    /**
     * Generates a list box.
     *
     * @param string       $name      the input name
     * @param string|array $selection the selected value(s)
     * @param array        $items     the option data items. The array keys are option values, and the array values
     *                                are the corresponding option labels. The array can also be nested (i.e. some
     *                                array values are arrays too). For each sub-array, an option group will be
     *                                generated whose label is the key associated with the sub-array. If you have a
     *                                list of data models, you may convert them into the format described above using
     *                                [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array        $options   the tag options in terms of name-value pairs. The following options are specially
     *                                handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option;
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ~~~
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ~~~
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - unselect: string, the value that will be submitted when no option is selected.
     *   When this attribute is set, a hidden field will be generated so that if no option is selected in multiple
     *   mode, we can still obtain the posted unselect value.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to `false`.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated list box tag
     */
    public static function listBox($name, $selection = null, $items = [], $options = [])
    {
        if (!array_key_exists('size', $options)) {
            $options['size'] = 4;
        }
        if (!empty($options['multiple']) && !empty($name) && substr_compare($name, '[]', -2, 2)) {
            $name .= '[]';
        }
        $options['name'] = $name;
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            if (!empty($name) && substr_compare($name, '[]', -2, 2) === 0) {
                $name = substr($name, 0, -2);
            }
            $hidden = static::hiddenInput($name, $options['unselect']);
            unset($options['unselect']);
        } else {
            $hidden = '';
        }
        $selectOptions = static::renderSelectOptions($selection, $items, $options);

        return $hidden . static::tag('select', "\n" . $selectOptions . "\n", $options);
    }

    /**
     * Generates a hidden input field.
     *
     * @param string $name    the name attribute.
     * @param string $value   the value attribute. If it is null, the value attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated hidden input tag
     */
    public static function hiddenInput($name, $value = null, $options = [])
    {
        return static::input('hidden', $name, $value, $options);
    }

    /**
     * Renders the option tags that can be used by [[dropDownList()]] and [[listBox()]].
     *
     * @param string|array|null $selection the selected value(s). This can be either a string for single selection
     *                                     or an array for multiple selections.
     * @param array $items the option data items. The array keys are option values, and the array values
     *                     are the corresponding option labels. The array can also be nested (i.e. some
     *                     array values are arrays too). For each sub-array, an option group will be
     *                     generated whose label is the key associated with the sub-array. If you have a
     *                     list of data models, you may convert them into the format described above using
     *                     [[\yii\helpers\ArrayHelper::map()]].
     *
     *                     Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     *                     the labels will also be HTML-encoded.
     * @param array $tagOptions the $options parameter that is passed to the [[dropDownList()]] or [[listBox()]]
     *                          call. This method will take out these elements, if any: "prompt", "options" and
     *                          "groups". See more details in [[dropDownList()]] for the explanation of these
     *                          elements.
     *
     * @return string the generated list options
     */
    public static function renderSelectOptions($selection, $items, &$tagOptions = [])
    {
        $lines = [];
        $encodeSpaces = ArrayHelper::remove($tagOptions, 'encodeSpaces', false);
        if (isset($tagOptions['prompt'])) {
            $prompt = $encodeSpaces ? str_replace(' ', '&nbsp;',
                static::encode($tagOptions['prompt'])) : static::encode($tagOptions['prompt']);
            $lines[] = static::tag('option', $prompt, ['value' => '']);
        }

        $options = isset($tagOptions['options']) ? $tagOptions['options'] : [];
        $groups = isset($tagOptions['groups']) ? $tagOptions['groups'] : [];
        unset($tagOptions['prompt'], $tagOptions['options'], $tagOptions['groups']);
        $options['encodeSpaces'] = ArrayHelper::getValue($options, 'encodeSpaces', $encodeSpaces);

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $groupAttrs = isset($groups[$key]) ? $groups[$key] : [];
                $groupAttrs['label'] = $key;
                $attrs = [
                    'options'      => $options,
                    'groups'       => $groups,
                    'encodeSpaces' => $encodeSpaces,
                ];
                $content = static::renderSelectOptions($selection, $value, $attrs);
                $lines[] = static::tag('optgroup', "\n" . $content . "\n", $groupAttrs);
            } else {
                $attrs = isset($options[$key]) ? $options[$key] : [];
                $attrs['value'] = (string)$key;
                $attrs['selected'] = $selection !== null &&
                    ((!is_array($selection) && !strcmp($key, $selection))
                        || (is_array($selection) && in_array($key, $selection, true)));
                $lines[] = static::tag('option',
                    ($encodeSpaces ? str_replace(' ', '&nbsp;',
                        static::encode($value)) : static::encode($value)),
                    $attrs);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generates a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     *
     * @param string       $name      the name attribute of each checkbox.
     * @param string|array $selection the selected value(s).
     * @param array        $items     the data item used to generate the checkboxes.
     *                                The array keys are the checkbox values, while the array values are the
     *                                corresponding labels.
     * @param array        $options   options (name => config) for the checkbox list container tag.
     *                                The following options are specially handled:
     *
     * - tag: string, the tag name of the container element.
     * - unselect: string, the value that should be submitted when none of the checkboxes is selected.
     *   By setting this option, a hidden input will be generated.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[checkbox()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ~~~
     *   function ($index, $label, $name, $checked, $value)
     *   ~~~
     *
     *   where $index is the zero-based index of the checkbox in the whole list; $label
     *   is the label for the checkbox; and $name, $value and $checked represent the name,
     *   value and the checked status of the checkbox input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated checkbox list
     */
    public static function checkboxList($name, $selection = null, $items = [], $options = [])
    {
        if (substr($name, -2) !== '[]') {
            $name .= '[]';
        }

        $formatter = isset($options['item']) ? $options['item'] : null;
        $itemOptions = isset($options['itemOptions']) ? $options['itemOptions'] : [];
        $encode = !isset($options['encode']) || $options['encode'];
        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null &&
                (!is_array($selection) && !strcmp($value, $selection)
                    || is_array($selection) && in_array($value, $selection, true));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::checkbox($name, $checked, array_merge($itemOptions, [
                    'value' => $value,
                    'label' => $encode ? static::encode($label) : $label,
                ]));
            }
            $index++;
        }

        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $name2 = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;
            $hidden = static::hiddenInput($name2, $options['unselect']);
        } else {
            $hidden = '';
        }
        $separator = isset($options['separator']) ? $options['separator'] : "\n";

        $tag = isset($options['tag']) ? $options['tag'] : 'div';
        unset($options['tag'], $options['unselect'], $options['encode'], $options['separator'], $options['item'], $options['itemOptions']);

        return $hidden . static::tag($tag, implode($separator, $lines), $options);
    }

    /**
     * Generates a checkbox input.
     *
     * @param string  $name    the name attribute.
     * @param boolean $checked whether the checkbox should be checked.
     * @param array   $options the tag options in terms of name-value pairs. The following options are specially
     *                         handled:
     *
     * - uncheck: string, the value associated with the uncheck state of the checkbox. When this attribute
     *   is present, a hidden input will be generated so that if the checkbox is not checked and is submitted,
     *   the value of this attribute will still be submitted to the server via the hidden input.
     * - label: string, a label displayed next to the checkbox.  It will NOT be HTML-encoded. Therefore you can pass
     *   in HTML code such as an image tag. If this is is coming from end users, you should [[encode()]] it to prevent
     *   XSS attacks. When this option is specified, the checkbox will be enclosed by a label tag.
     * - labelOptions: array, the HTML attributes for the label tag. Do not set this option unless you set the "label"
     * option.
     *
     * The rest of the options will be rendered as the attributes of the resulting checkbox tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated checkbox tag
     */
    public static function checkbox($name, $checked = false, $options = [])
    {
        $options['checked'] = (bool)$checked;
        $value = array_key_exists('value', $options) ? $options['value'] : '1';
        if (isset($options['uncheck'])) {
            // add a hidden field so that if the checkbox is not selected, it still submits a value
            $hidden = static::hiddenInput($name, $options['uncheck']);
            unset($options['uncheck']);
        } else {
            $hidden = '';
        }
        if (isset($options['label'])) {
            $label = $options['label'];
            $labelOptions = isset($options['labelOptions']) ? $options['labelOptions'] : [];
            unset($options['label'], $options['labelOptions']);
            $content = static::label(static::input('checkbox', $name, $value, $options) . ' ' . $label, null,
                $labelOptions);

            return $hidden . $content;
        } else {
            return $hidden . static::input('checkbox', $name, $value, $options);
        }
    }

    /**
     * Generates a label tag.
     *
     * @param string $content label text. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     *                        such as an image tag. If this is is coming from end users, you should [[encode()]]
     *                        it to prevent XSS attacks.
     * @param string $for     the ID of the HTML element that this label is associated with.
     *                        If this is null, the "for" attribute will not be generated.
     * @param array  $options the tag options in terms of name-value pairs. These will be rendered as
     *                        the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     *                        If a value is null, the corresponding attribute will not be rendered.
     *                        See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated label tag
     */
    public static function label($content, $for = null, $options = [])
    {
        $options['for'] = $for;

        return static::tag('label', $content, $options);
    }

    /**
     * Generates a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     *
     * @param string       $name      the name attribute of each radio button.
     * @param string|array $selection the selected value(s).
     * @param array        $items     the data item used to generate the radio buttons.
     *                                The array keys are the radio button values, while the array values are the
     *                                corresponding labels.
     * @param array        $options   options (name => config) for the radio button list. The following options are
     *                                supported:
     *
     * - unselect: string, the value that should be submitted when none of the radio buttons is selected.
     *   By setting this option, a hidden input will be generated.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[radio()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ~~~
     *   function ($index, $label, $name, $checked, $value)
     *   ~~~
     *
     *   where $index is the zero-based index of the radio button in the whole list; $label
     *   is the label for the radio button; and $name, $value and $checked represent the name,
     *   value and the checked status of the radio button input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated radio button list
     */
    public static function radioList($name, $selection = null, $items = [], $options = [])
    {
        $encode = !isset($options['encode']) || $options['encode'];
        $formatter = isset($options['item']) ? $options['item'] : null;
        $itemOptions = isset($options['itemOptions']) ? $options['itemOptions'] : [];
        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null &&
                (!is_array($selection) && !strcmp($value, $selection)
                    || is_array($selection) && in_array($value, $selection, true));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::radio($name, $checked, array_merge($itemOptions, [
                    'value' => $value,
                    'label' => $encode ? static::encode($label) : $label,
                ]));
            }
            $index++;
        }

        $separator = isset($options['separator']) ? $options['separator'] : "\n";
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $hidden = static::hiddenInput($name, $options['unselect']);
        } else {
            $hidden = '';
        }

        $tag = isset($options['tag']) ? $options['tag'] : 'div';
        unset($options['tag'], $options['unselect'], $options['encode'], $options['separator'], $options['item'], $options['itemOptions']);

        return $hidden . static::tag($tag, implode($separator, $lines), $options);
    }

    /**
     * Generates a radio button input.
     *
     * @param string  $name    the name attribute.
     * @param boolean $checked whether the radio button should be checked.
     * @param array   $options the tag options in terms of name-value pairs. The following options are specially
     *                         handled:
     *
     * - uncheck: string, the value associated with the uncheck state of the radio button. When this attribute
     *   is present, a hidden input will be generated so that if the radio button is not checked and is submitted,
     *   the value of this attribute will still be submitted to the server via the hidden input.
     * - label: string, a label displayed next to the radio button.  It will NOT be HTML-encoded. Therefore you can
     * pass
     *   in HTML code such as an image tag. If this is is coming from end users, you should [[encode()]] it to prevent
     *   XSS attacks. When this option is specified, the radio button will be enclosed by a label tag.
     * - labelOptions: array, the HTML attributes for the label tag. Do not set this option unless you set the "label"
     * option.
     *
     * The rest of the options will be rendered as the attributes of the resulting radio button tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated radio button tag
     */
    public static function radio($name, $checked = false, $options = [])
    {
        $options['checked'] = (bool)$checked;
        $value = array_key_exists('value', $options) ? $options['value'] : '1';
        if (isset($options['uncheck'])) {
            // add a hidden field so that if the radio button is not selected, it still submits a value
            $hidden = static::hiddenInput($name, $options['uncheck']);
            unset($options['uncheck']);
        } else {
            $hidden = '';
        }
        if (isset($options['label'])) {
            $label = $options['label'];
            $labelOptions = isset($options['labelOptions']) ? $options['labelOptions'] : [];
            unset($options['label'], $options['labelOptions']);
            $content = static::label(static::input('radio', $name, $value, $options) . ' ' . $label, null,
                $labelOptions);

            return $hidden . $content;
        } else {
            return $hidden . static::input('radio', $name, $value, $options);
        }
    }

    /**
     * Generates an ordered list.
     *
     * @param array|\Traversable $items   the items for generating the list. Each item generates a single list item.
     *                                    Note that items will be automatically HTML encoded if `$options['encode']` is
     *                                    not set or true.
     * @param array              $options options (name => config) for the radio button list. The following options are
     *                                    supported:
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is
     * specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ~~~
     *   function ($item, $index)
     *   ~~~
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated ordered list. An empty string is returned if `$items` is empty.
     */
    public static function ol($items, $options = [])
    {
        $options['tag'] = 'ol';

        return static::ul($items, $options);
    }

    /**
     * Generates an unordered list.
     *
     * @param array|\Traversable $items   the items for generating the list. Each item generates a single list item.
     *                                    Note that items will be automatically HTML encoded if `$options['encode']` is
     *                                    not set or true.
     * @param array              $options options (name => config) for the radio button list. The following options are
     *                                    supported:
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is
     * specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ~~~
     *   function ($item, $index)
     *   ~~~
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated unordered list. An empty list tag will be returned if `$items` is empty.
     */
    public static function ul($items, $options = [])
    {
        $tag = isset($options['tag']) ? $options['tag'] : 'ul';
        $encode = !isset($options['encode']) || $options['encode'];
        $formatter = isset($options['item']) ? $options['item'] : null;
        $itemOptions = isset($options['itemOptions']) ? $options['itemOptions'] : [];
        unset($options['tag'], $options['encode'], $options['item'], $options['itemOptions']);

        if (empty($items)) {
            return static::tag($tag, '', $options);
        }

        $results = [];
        foreach ($items as $index => $item) {
            if ($formatter !== null) {
                $results[] = call_user_func($formatter, $item, $index);
            } else {
                $results[] = static::tag('li', $encode ? static::encode($item) : $item, $itemOptions);
            }
        }

        return static::tag($tag, "\n" . implode("\n", $results) . "\n", $options);
    }

    /**
     * Adds a CSS class to the specified options.
     * If the CSS class is already in the options, it will not be added again.
     *
     * @param array  $options the options to be modified.
     * @param string $class   the CSS class to be added
     */
    public static function addCssClass(&$options, $class)
    {
        if (isset($options['class'])) {
            $classes = ' ' . $options['class'] . ' ';
            if (strpos($classes, ' ' . $class . ' ') === false) {
                $options['class'] .= ' ' . $class;
            }
        } else {
            $options['class'] = $class;
        }
    }

    /**
     * Removes a CSS class from the specified options.
     *
     * @param array  $options the options to be modified.
     * @param string $class   the CSS class to be removed
     */
    public static function removeCssClass(&$options, $class)
    {
        if (isset($options['class'])) {
            $classes = array_unique(preg_split('/\s+/', $options['class'] . ' ' . $class, -1,
                PREG_SPLIT_NO_EMPTY));
            if (($index = array_search($class, $classes)) !== false) {
                unset($classes[$index]);
            }
            if (empty($classes)) {
                unset($options['class']);
            } else {
                $options['class'] = implode(' ', $classes);
            }
        }
    }

    /**
     * Returns the real attribute name from the given attribute expression.
     *
     * An attribute expression is an attribute name prefixed and/or suffixed with array indexes.
     * It is mainly used in tabular data input and/or input of array type. Below are some examples:
     *
     * - `[0]content` is used in tabular data input to represent the "content" attribute
     *   for the first model in tabular input;
     * - `dates[0]` represents the first array element of the "dates" attribute;
     * - `[0]dates[0]` represents the first array element of the "dates" attribute
     *   for the first model in tabular input.
     *
     * If `$attribute` has neither prefix nor suffix, it will be returned back without change.
     *
     * @param string $attribute the attribute name or expression
     *
     * @return string the attribute name without prefix and suffix.
     * @throws \InvalidArgumentException if the attribute name contains non-word characters.
     */
    public static function getAttributeName($attribute)
    {
        if (preg_match('/(^|.*\])([\w\.]+)(\[.*|$)/', $attribute, $matches)) {
            return $matches[2];
        } else {
            throw new \InvalidArgumentException('Attribute name must contain word characters only.');
        }
    }
}