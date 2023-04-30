<?php

use Fusio\Adapter\Fcgi\Action\FcgiEngine;
use Fusio\Adapter\Fcgi\Action\FcgiProcessor;
use Fusio\Engine\Adapter\ServiceBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = ServiceBuilder::build($container);
    $services->set(FcgiEngine::class);
    $services->set(FcgiProcessor::class);
};
