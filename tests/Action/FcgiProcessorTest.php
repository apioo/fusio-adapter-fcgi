<?php
/*
 * Fusio is an open source API management platform which helps to create innovative API solutions.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Adapter\Fcgi\Tests\Action;

use Fusio\Adapter\Fcgi\Action\FcgiProcessor;
use Fusio\Adapter\Fcgi\Tests\FcgiTestCase;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * FcgiProcessorTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class FcgiProcessorTest extends FcgiTestCase
{
    public function testHandle()
    {
        $this->pingFastCGIServer();

        $action = $this->getActionFactory()->factory(FcgiProcessor::class);
        $script = realpath(__DIR__ . '/../json.php');

        // handle request
        $response = $action->handle(
            $this->getRequest('GET'),
            $this->getParameters(['host' => '127.0.0.1', 'port' => 9090, 'script' => $script]),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $script = json_encode($script);
        $expect = <<<JSON
{
    "foo": "bar",
    "body": {},
    "server": {
        "REQUEST_METHOD": "GET",
        "REQUEST_URI": "",
        "SCRIPT_FILENAME": {$script},
        "CONTENT_TYPE": "application\/json",
        "OPERATION_ID": "34",
        "REMOTE_ADDR": "127.0.0.1",
        "REMOTE_USER": "Consumer",
        "USER_ANONYMOUS": "0",
        "USER_ID": "2",
        "APP_ID": "3",
        "APP_KEY": "5347307d-d801-4075-9aaa-a21a29a448c5"
    }
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode(), $actual);
        $this->assertEquals(['content-type' => 'application/json'], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandlePost()
    {
        $this->pingFastCGIServer();

        $action = $this->getActionFactory()->factory(FcgiProcessor::class);
        $script = realpath(__DIR__ . '/../json.php');

        // handle request
        $response = $action->handle(
            $this->getRequest('POST', [], [], [], new Record(['foo' => 'bar'])),
            $this->getParameters(['host' => '127.0.0.1', 'port' => 9090, 'script' => $script]),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $script = json_encode($script);
        $expect = <<<JSON
{
    "foo": "bar",
    "body": {
        "foo": "bar"
    },
    "server": {
        "REQUEST_METHOD": "POST",
        "REQUEST_URI": "",
        "SCRIPT_FILENAME": {$script},
        "CONTENT_TYPE": "application\/json",
        "OPERATION_ID": "34",
        "REMOTE_ADDR": "127.0.0.1",
        "REMOTE_USER": "Consumer",
        "USER_ANONYMOUS": "0",
        "USER_ID": "2",
        "APP_ID": "3",
        "APP_KEY": "5347307d-d801-4075-9aaa-a21a29a448c5"
    }
}
JSON;

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['content-type' => 'application/json'], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);
    }

    public function testHandleHtml()
    {
        $this->pingFastCGIServer();

        $action = $this->getActionFactory()->factory(FcgiProcessor::class);
        $script = realpath(__DIR__ . '/../html.php');

        // handle request
        $response = $action->handle(
            $this->getRequest('GET'),
            $this->getParameters(['host' => '127.0.0.1', 'port' => 9090, 'script' => $script]),
            $this->getContext()
        );

        $actual = $response->getBody();
        $expect = 'foobar';

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode(), $actual);
        $this->assertEquals(['content-type' => 'text/html;charset=UTF-8'], $response->getHeaders());
        $this->assertEquals($expect, $actual, $actual);
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
