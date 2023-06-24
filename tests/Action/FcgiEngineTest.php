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

namespace Fusio\Adapter\Fcgi\Tests\Action;

use Fusio\Adapter\Fcgi\Action\FcgiEngine;
use Fusio\Adapter\Fcgi\Tests\FcgiTestCase;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PHPUnit\Framework\TestCase;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * FcgiEngineTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class FcgiEngineTest extends FcgiTestCase
{
    public function testHandle()
    {
        $this->pingFastCGIServer();

        $script = realpath(__DIR__ . '/../json.php');

        /** @var FcgiEngine $action */
        $action = $this->getActionFactory()->factory(FcgiEngine::class);
        $action->setHost('127.0.0.1');
        $action->setPort(9090);
        $action->setScript($script);

        // handle request
        $response = $action->handle(
            $this->getRequest('POST', [], [], [], new Record(['foo' => 'bar'])),
            $this->getParameters(),
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
        "REMOTE_ADDR": "127.0.0.1",
        "REMOTE_USER": "Consumer",
        "OPERATION_ID": "34",
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

        /** @var FcgiEngine $action */
        $action = $this->getActionFactory()->factory(FcgiEngine::class);
        $action->setHost('127.0.0.1');
        $action->setPort(9090);
        $action->setScript(realpath(__DIR__ . '/../html.php'));

        // handle request
        $response = $action->handle(
            $this->getRequest('GET'),
            $this->getParameters(),
            $this->getContext()
        );

        $actual = $response->getBody();
        $expect = 'foobar';

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode(), $actual);
        $this->assertEquals(['content-type' => 'text/html;charset=UTF-8'], $response->getHeaders());
        $this->assertEquals($expect, $actual, $actual);
    }

    private function pingFastCGIServer(): void
    {
        $handle = @stream_socket_client('tcp://127.0.0.1:9090', $errno, $errstr, 2);
        if (!$handle) {
            $this->markTestSkipped('FastCGI server not available: ' . $errstr);
        }
    }
}
