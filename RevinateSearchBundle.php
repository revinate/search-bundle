<?php
namespace Revinate\SearchBundle;

use Revinate\SearchBundle\Service\RevinateSearchListenerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RevinateSearchBundle extends Bundle
{
    public function build(ContainerBuilder $container) {
        parent::build($container);
        $container->addCompilerPass(new RevinateSearchListenerCompilerPass());
    }
}