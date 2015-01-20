<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 16:56
 */

namespace Smartling\Helpers;


class Options {
    const SMARTLING_ACCOUNT_INFO = "smartling_options";
    const SMARTLING_LOCALES = "smartling_locales";
    private $retrievalTypes = array(
        "pseudo",
        "published",
        "pending"
    );

    /**
     * @var AccountInfo
     */
    private $accountInfo;
    /**
     * @var Locales
     */
    private $locales;

    function __construct()
    {
        $this->accountInfo = new AccountInfo();
        $this->locales = new Locales();
        $this->get();
    }

    public function save(){
        $this->getAccountInfo()->save(self::SMARTLING_ACCOUNT_INFO);
        $this->getLocales()->save(self::SMARTLING_LOCALES);
    }

    public function get() {
        $this->getAccountInfo()->get(self::SMARTLING_ACCOUNT_INFO);
        $this->getLocales()->get(self::SMARTLING_LOCALES);
    }

    /**
     * @return AccountInfo
     */
    public function getAccountInfo()
    {
        return $this->accountInfo;
    }

    /**
     * @return Locales
     */
    public function getLocales()
    {
        return $this->locales;
    }

    /**
     * @return array
     */
    public function getRetrievalTypes()
    {
        return $this->retrievalTypes;
    }
}

class AccountInfo {
    private $apiUrl = "https://api.smartling.com/v1";
    private $projectId;
    private $key;
    private $retrievalType;
    private $callBackUrl = false;
    private $autoAuthorize = false;

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @param string $apiUrl
     */
    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return mixed
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * @param mixed $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return mixed
     */
    public function getRetrievalType()
    {
        return $this->retrievalType;
    }

    /**
     * @param mixed $retrievalType
     */
    public function setRetrievalType($retrievalType)
    {
        $this->retrievalType = $retrievalType;
    }

    /**
     * @return boolean
     */
    public function getCallBackUrl()
    {
        return $this->callBackUrl;
    }

    /**
     * @param boolean $callBackUrl
     */
    public function setCallBackUrl($callBackUrl)
    {
        $this->callBackUrl = $callBackUrl;
    }

    /**
     * @return boolean
     */
    public function getAutoAuthorize()
    {
        return $this->autoAuthorize;
    }

    /**
     * @param boolean $autoAuthorize
     */
    public function setAutoAuthorize($autoAuthorize)
    {
        $this->autoAuthorize = $autoAuthorize;
    }

    public function get($key) {
        $values = get_site_option($key);
        if($values) {
            $this->setApiUrl($values["apiUrl"]);
            $this->setProjectId($values["projectId"]);
            $this->setKey($values["key"]);
            $this->setRetrievalType($values["retrievalType"]);
            $this->setCallBackUrl($values["callBackUrl"]);
            $this->setAutoAuthorize($values["autoAuthorize"]);
        }
        return $values;
    }

    public function save($key) {
        $option = get_site_option($key);
        $values = $this->toArray();
        if (!$option ) {
            add_site_option($key, $values);
        } else {
            update_site_option($key, $values);
        }
    }

    public function toArray() {
        return array(
            "apiUrl" => trim($this->getApiUrl()),
            "projectId" => trim($this->getProjectId()),
            "key" => trim($this->getKey()),
            "retrievalType" => trim($this->getRetrievalType()),
            "callBackUrl" => trim($this->getCallBackUrl()),
            "autoAuthorize" => trim($this->getAutoAuthorize())
        );
    }
}

class Locales {

    /**
     * @var string
     */
    private $defaultLocale;
    /**
     * @var TargetLocale[]
     */
    private $targetLocales;

    function __construct()
    {
        $this->targetLocales = array();
    }

    public function get($key) {
        $values = get_site_option($key);
        if($values) {
            $this->setDefaultLocale($values["defaultLocale"]);
            $this->setTargetLocales($values["targetLocales"]);
        }
        return $values;
    }

    public function save($key) {
        $option = get_site_option($key);
        $values = $this->toArray();

        if (!$option ) {
            add_site_option($key, $values);
        } else {
            update_site_option($key, $values);
        }
    }

    public function toArray() {
        $targetLocales = array();
        foreach($this->getTargetLocales(true) as $targetLocale) {
            $targetLocales[] = $targetLocale->toArray();
        }
        return array(
            "defaultLocale" => trim($this->getDefaultLocale()),
            "targetLocales" => $targetLocales
        );
    }

    /**
     * @return TargetLocale[]
     */
    public function getTargetLocales($addDefault = false)
    {
        $locales = array();
        foreach($this->targetLocales as $target) {
            if($addDefault || $target->getLocale() != $this->getDefaultLocale()) {
                $locales[] = $target;
            }
        }
        return $locales;
    }

    /**
     * @param array $targetLocales
     */
    public function setTargetLocales($targetLocales)
    {
        $this->targetLocales = $this->parseTargetLocales($targetLocales);
    }

    /**
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * @param string $defaultLocale
     */
    public function setDefaultLocale($defaultLocale)
    {
        $this->defaultLocale = $defaultLocale;
    }

    private function parseTargetLocales($targetLocales) {
        $locales = array();
        if($targetLocales) {
            foreach($targetLocales as $raw) {
                $locales[] = new TargetLocale($raw["locale"], $raw["target"], $raw["enabled"]);
            }
        }
        return $locales;
    }
}

class TargetLocale {
    /**
     * @var string
     */
    private $locale;
    /**
     * @var string
     */
    private $target;
    /**
     * @var boolean
     */
    private $enabled;

    function __construct($locale, $target, $enabled)
    {
        $this->locale = $locale;
        $this->target = $target;
        $this->enabled = $enabled;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param boolean $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    public function toArray() {
        return array(
            "locale" => $this->getLocale(),
            "target" => $this->getTarget(),
            "enabled" => $this->getEnabled()
        );
    }
}