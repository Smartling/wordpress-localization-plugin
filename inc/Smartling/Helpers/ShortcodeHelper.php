<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Helpers\EventParameters\TranslationStringFilterParameters;
use Smartling\WP\WPHookInterface;

/**
 * Class ShortcodeHelper
 * @package Smartling\Helpers
 */
class ShortcodeHelper implements WPHookInterface
{
    /**
     * Regular expression to work with shortcodes, took from do_shortcode() function
     */
    const WP_SHORTCODE_REGEXP = '@\[([^<>&/\[\]\x00-\x20=]++)@';

    const SMARTLING_SHORTCODE_MASK = '##';

    public static function getShortcodeMaskRegexp()
    {
        return str_replace('%s', self::SMARTLING_SHORTCODE_MASK, '(%s\[[^\]]+\]%s|%s\[[^\]]+\]|\[[^\]]+\]%s)');
    }

    /**
     * @var array
     */
    private $initialShortcodeHandlers;

    /**
     * @var TranslationStringFilterParameters
     */
    private $params;

    /**
     * @return array
     */
    protected function getInitialShortcodeHandlers()
    {
        return $this->initialShortcodeHandlers;
    }

    /**
     * @param array $initialShortcodeHandlers
     */
    protected function setInitialShortcodeHandlers($initialShortcodeHandlers)
    {
        $this->initialShortcodeHandlers = $initialShortcodeHandlers;
    }

    /**
     * @return TranslationStringFilterParameters
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param TranslationStringFilterParameters $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    public function __destruct()
    {
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->restoreShortcodeHandler();
        }
    }

    /**
     * @return array
     */
    private function getShortcodeAssignments()
    {
        global $shortcode_tags;

        return $shortcode_tags;
    }

    private function setShortcodeAssignments(array $assignments)
    {
        global $shortcode_tags;

        $shortcode_tags = $assignments;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING, [$this, 'processString']);
        add_filter(ExportedAPI::FILTER_SMARTLING_TRANSLATION_STRING_RECEIVED,[$this, 'processTranslation']);
    }

    public function processTranslation(TranslationStringFilterParameters $params)
    {
        $this->setParams($params);
        $this->unmaskShortcodes();
        return $this->getParams();
    }
    
    public function processString(TranslationStringFilterParameters $params)
    {
        $this->setParams($params);
        $string = $params->getNode()->nodeValue;
        if ($this->stringHasShortcode($string)) {
            $detectedShortcodes = $this->getShortcodes($string);
            $this->replaceShortcodeHandler($detectedShortcodes);
            do_shortcode($string);
            $this->restoreShortcodeHandler();
            return $params;
        } else {
            return $params;
        }
    }

    /**
     * replaces all shortcode handlers to extract data
     *
     * @param array       $attributes
     * @param string|null $content
     * @param string      $name
     */
    public function shortcodeHandler($attributes, $content = null, $name)
    {
        $this->maskShortcode($name);
        if (null !== $content) {
            return do_shortcode($content);
        }
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function stringHasShortcode($string)
    {
        return preg_match(self::WP_SHORTCODE_REGEXP, $string) > 0;
    }


    private function getShortcodes($string)
    {
        $matches = [];
        preg_match_all(self::WP_SHORTCODE_REGEXP, $string, $matches);

        return array_key_exists(1, $matches) ? $matches[1] : [];
    }

    private function replaceShortcodeHandler($shortcodes)
    {
        $activeShortcodeAssignments = $this->getShortcodeAssignments();
        $this->setInitialShortcodeHandlers($activeShortcodeAssignments);

        foreach ($shortcodes as $shortcodeName) {
            $activeShortcodeAssignments[$shortcodeName] = [$this, 'shortcodeHandler'];
        }
        $this->setShortcodeAssignments($activeShortcodeAssignments);

    }

    private function restoreShortcodeHandler()
    {
        if (null !== $this->getInitialShortcodeHandlers()) {
            $this->setShortcodeAssignments($this->getInitialShortcodeHandlers());
            $this->setInitialShortcodeHandlers(null);
        }
    }

    private function maskShortcode($name)
    {
        $initialString = $this->getParams()->getNode()->nodeValue;

        $matches = [];
        preg_match_all(vsprintf('/%s/', [get_shortcode_regex([$name])]), $initialString, $matches);
        $shortcode = $matches[0][0];
        $masked = vsprintf('%s%s%s', [self::SMARTLING_SHORTCODE_MASK, $matches[0][0], self::SMARTLING_SHORTCODE_MASK]);

        $result = str_replace($shortcode, $masked, $initialString);

        $updatedCdata = new \DOMCdataSection($result);

        $c = $this->getParams()->getNode()->childNodes;

        foreach ($c as $node) {
            /**
             * @var \DOMNode $node
             */
            if (XML_CDATA_SECTION_NODE === $node->nodeType) {
                $this->getParams()->getNode()->removeChild($node);
            }

        }
        $this->getParams()->getNode()->appendChild($updatedCdata);
    }

    private function unmaskShortcodes()
    {
        $string = $this->getParams()->getNode()->nodeValue;

        $string = preg_replace('/##\[/','[',$string);
        $string = preg_replace('/\]##/',']',$string);

        $updatedCdata = new \DOMCdataSection($string);

        $c = $this->getParams()->getNode()->childNodes;

        foreach ($c as $node) {
            /**
             * @var \DOMNode $node
             */
            if (XML_CDATA_SECTION_NODE === $node->nodeType) {
                $this->getParams()->getNode()->removeChild($node);
            }

        }
        $this->getParams()->getNode()->appendChild($updatedCdata);
    }


}