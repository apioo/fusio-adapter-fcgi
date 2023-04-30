<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Adapter\Fcgi\Tests;

use Fusio\Adapter\Aws\Action\AwsLambdaInvoke;
use Fusio\Adapter\Aws\Connection\Aws;
use Fusio\Adapter\Aws\Generator\AwsLambda;
use Fusio\Adapter\Beanstalk\Action\BeanstalkPublish;
use Fusio\Adapter\Beanstalk\Connection\Beanstalk;
use Fusio\Adapter\Fcgi\Action\FcgiEngine;
use Fusio\Adapter\Fcgi\Action\FcgiProcessor;
use Fusio\Engine\Action\Runtime;
use Fusio\Engine\ConnectorInterface;
use Fusio\Engine\Model\Connection;
use Fusio\Engine\Parameters;
use Fusio\Engine\Test\CallbackConnection;
use Fusio\Engine\Test\EngineTestCaseTrait;
use Pheanstalk\Pheanstalk;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * FcgiTestCase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
abstract class FcgiTestCase extends TestCase
{
    use EngineTestCaseTrait;

    protected function configure(Runtime $runtime, Container $container): void
    {
        $container->set(FcgiEngine::class, new FcgiEngine($runtime));
        $container->set(FcgiProcessor::class, new FcgiProcessor($runtime));
    }
}
