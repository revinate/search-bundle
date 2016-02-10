<?php

namespace Revinate\SearchBundle\Service;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RevinateSearchListenerCompilerPass implements CompilerPassInterface {
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container) {
        // Register the event listeners
        $taggedEventListeners = $container->findTaggedServiceIds('revinate_search.event_listener');
        if (empty($taggedEventListeners)) {
            return;
        }

        $eventManagerDefinition = $container->getDefinition('revinate_search.internal.event_manager');

        foreach ($taggedEventListeners as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['event'])) {
                    $eventManagerDefinition->addMethodCall('addEventListener', [$tag['event'], new Reference($serviceId)]);
                }
            }
        }
    }
}