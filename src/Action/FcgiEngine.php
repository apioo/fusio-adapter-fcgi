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

namespace Fusio\Adapter\Fcgi\Action;

use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\DeleteRequest;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PatchRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Requests\PutRequest;
use hollodotme\FastCGI\SocketConnections;
use PSX\Http\MediaType;

/**
 * FcgiEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class FcgiEngine extends ActionAbstract
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var integer
     */
    protected $port;

    /**
     * @var string
     */
    protected $script;

    public function __construct($host = null, $port = null, $script = null)
    {
        $this->host   = $host;
        $this->port   = $port;
        $this->script = $script;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function setScript($script)
    {
        $this->script = $script;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        if (empty($this->port)) {
            $connection = new SocketConnections\UnixDomainSocket($this->host);
        } else {
            $connection = new SocketConnections\NetworkSocket($this->host, $this->port);
        }

        $client   = new Client();
        $request  = $this->newRequest($request->getMethod(), $this->script, \json_encode($request->getBody()), $context);
        $response = $client->sendRequest($connection, $request);

        $headers = $response->getHeaders();
        $headers = array_change_key_case($headers);
        $body    = $response->getBody();

        $headers = array_map(static function($value) {
            return is_array($value) ? implode(', ', $value) : $value;
        }, $headers);

        $code = 200;
        if (isset($headers['status'])) {
            $code = (int) strstr($headers['status'], ' ', true);
            unset($headers['status']);
        }

        $contentType = $headers['content-type'] ?? null;
        if (!empty($contentType)) {
            if ($this->isJson($contentType)) {
                $body = \json_decode($body);
            }
        }

        if (isset($headers['x-powered-by'])) {
            unset($headers['x-powered-by']);
        }

        return $this->response->build($code, $headers, $body);
    }

    private function isJson($contentType)
    {
        if (!empty($contentType)) {
            try {
                return MediaType\Json::isMediaType(new MediaType($contentType));
            } catch (\InvalidArgumentException $e) {
            }
        }

        return false;
    }

    private function newRequest($method, $script, $body, ContextInterface $context)
    {
        switch ($method) {
            case 'DELETE':
                $request = new DeleteRequest($script, $body);
                break;

            case 'GET':
                $request = new GetRequest($script, $body);
                break;

            case 'PATCH':
                $request = new PatchRequest($script, $body);
                break;

            case 'POST':
                $request = new PostRequest($script, $body);
                break;

            case 'PUT':
                $request = new PutRequest($script, $body);
                break;

            default:
                throw new \RuntimeException('Invalid request method');
                break;
        }

        $request->setContentType('application/json');
        $request->setRemoteAddress($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $request->setCustomVar('REMOTE_USER', $context->getUser()->getName());
        $request->setCustomVar('ROUTE_ID', $context->getRouteId());
        $request->setCustomVar('USER_ANONYMOUS', $context->getUser()->isAnonymous() ? '1' : '0');
        $request->setCustomVar('USER_ID', $context->getUser()->getId());
        $request->setCustomVar('APP_ID', $context->getApp()->getId());
        $request->setCustomVar('APP_KEY', $context->getApp()->getAppKey());

        if (!$context->getUser()->isAnonymous()) {
            $request->setCustomVar('AUTH_TYPE', 'Bearer');
        }

        return $request;
    }
}
