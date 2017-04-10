<?php

namespace Smartling\ContentTypes\AutoDiscover;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PostTypes
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

    public function __construct(ContainerBuilder $di)
    {

        $this->setDi($di);
        $this->setLogger($di->get('logger'));
        add_action('registered_post_type', [$this, 'hookHandler']);
    }

    public function hookHandler($postType)
    {
        $msg = 'Detected post-type \'%s\' registration. Adding it to smartling-connector.';
        $this->getLogger()->debug(vsprintf($msg, [$postType]));

        add_action('smartling_register_custom_type', function (array $definition) use ($postType) {
            global $wp_post_types;

            if (true === $wp_post_types[$postType]->public) {
                return array_merge($definition, [
                    [
                        "type" =>
                            [
                                'identifier' => $postType,
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
            } else {
                return $definition;
            }
        }, 0, 1);
    }
}