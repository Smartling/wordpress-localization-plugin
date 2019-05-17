<?php

namespace Smartling\ContentTypes\AutoDiscover;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Taxonomies
{
    /**
     * @var ContainerBuilder
     */
    private $di;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $ignoredTypes;

    /**
     * @return ContainerBuilder
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param ContainerBuilder $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getIgnoredTypes()
    {
        return $this->ignoredTypes;
    }

    /**
     * @param array $ignoredTypes
     */
    public function setIgnoredTypes($ignoredTypes)
    {
        $this->ignoredTypes = $ignoredTypes;
    }

    public function __construct(ContainerBuilder $di)
    {
        $this->setDi($di);
        $this->setLogger($di->get('logger'));

        $ignoredTypes = $di->getParameter('ignoredTypes')['taxonomies'];
        $ignoredTypes = null === $ignoredTypes ? [] : $ignoredTypes;
        $this->setIgnoredTypes($ignoredTypes);

        add_action('registered_taxonomy', [$this, 'hookHandler']);
    }

    public function hookHandler($taxonomy)
    {
        if (in_array($taxonomy, $this->getIgnoredTypes(), true)) {
            return;
        }

        add_action('smartling_register_custom_taxonomy', function (array $definition) use ($taxonomy) {
            return array_merge(
                $definition,
                [
                    [
                        'taxonomy' => [
                            'identifier' => $taxonomy,
                            'widget'     => [
                                'visible' => true,
                            ],
                            'visibility' => [
                                'submissionBoard' => true,
                                'bulkSubmit'      => true,
                            ],
                        ],
                    ],
                ]);
        }, 0, 1);
    }
}