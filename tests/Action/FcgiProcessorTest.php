<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Fcgi\Tests\Action;

use Fusio\Adapter\Fcgi\Action\FcgiProcessor;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PSX\Http\Environment\HttpResponseInterface;

/**
 * FcgiProcessorTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class FcgiProcessorTest extends \PHPUnit_Framework_TestCase
{
    use EngineTestCaseTrait;

    protected function setUp()
    {
        parent::setUp();
    }

    public function testHandle()
    {
        $this->pingFastCGIServer();

        $action = $this->getActionFactory()->factory(FcgiProcessor::class);

        // handle request
        $response = $action->handle(
            $this->getRequest('GET'),
            $this->getParameters(['socket' => 'tcp://127.0.0.1:9090', 'script' => 'json.php']),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{
    "foo": "bar",
    "server": {
        "REQUEST_METHOD": "GET",
        "REQUEST_URI": "\/",
        "SCRIPT_FILENAME": "json.php",
        "CONTENT_TYPE": "application\/json",
        "REMOTE_ADDR": "127.0.0.1",
        "REMOTE_USER": "Consumer",
        "ROUTE_ID": "34",
        "USER_ANONYMOUS": "0",
        "USER_ID": "2",
        "APP_ID": "3",
        "APP_KEY": "5347307d-d801-4075-9aaa-a21a29a448c5"
    }
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode(), $actual);
        $this->assertEquals(['x-powered-by' => 'PHP/' . PHP_VERSION, 'content-type' => 'application/json'], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testGetForm()
    {
        $action  = $this->getActionFactory()->factory(FcgiProcessor::class);
        $builder = new Builder();
        $factory = $this->getFormElementFactory();

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }

    private function pingFastCGIServer()
    {
        $handle = @stream_socket_client('tcp://127.0.0.1:9090', $errno, $errstr, 2);
        if (!$handle) {
            $this->markTestSkipped('FastCGI server not available: ' . $errstr);
        }
    }
}
