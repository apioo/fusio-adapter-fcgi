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

namespace Fusio\Adapter\Fcgi\Action;

use Fusio\Engine\Action\RuntimeInterface;
use Fusio\Engine\ActionInterface;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequest;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;
use Fusio\Engine\Response\FactoryInterface;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\AbstractRequest;
use hollodotme\FastCGI\Requests\DeleteRequest;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PatchRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Requests\PutRequest;
use hollodotme\FastCGI\SocketConnections;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\InternalServerErrorException;
use PSX\Http\MediaType;

/**
 * FcgiEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class FcgiEngine implements ActionInterface
{
    private ?string $host = null;
    private ?int $port = null;
    private ?string $script = null;

    private FactoryInterface $response;

    public function __construct(RuntimeInterface $runtime)
    {
        $this->response = $runtime->getResponse();
    }

    public function setHost(?string $host): void
    {
        $this->host = $host;
    }

    public function setPort(?int $port): void
    {
        $this->port = $port;
    }

    public function setScript(?string $script): void
    {
        $this->script = $script;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $host = $this->host ?? throw new InternalServerErrorException('No host configured');
        $script = $this->script ?? throw new InternalServerErrorException('No script configured');

        if (empty($this->port)) {
            $connection = new SocketConnections\UnixDomainSocket($host);
        } else {
            $connection = new SocketConnections\NetworkSocket($host, $this->port);
        }

        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            $method = $requestContext->getRequest()->getMethod();
        } else {
            $method = 'POST';
        }

        $client   = new Client();
        $request  = $this->newRequest($method, $script, \json_encode($request->getPayload()), $context);
        $response = $client->sendRequest($connection, $request);

        $headers = $response->getHeaders();
        $headers = array_change_key_case($headers);
        $body    = $response->getBody();

        $headers = array_map(static function(array $value) {
            return implode(', ', $value);
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

    private function isJson(?string $contentType): bool
    {
        if (!empty($contentType)) {
            try {
                return MediaType\Json::isMediaType(MediaType::parse($contentType));
            } catch (\InvalidArgumentException $e) {
            }
        }

        return false;
    }

    private function newRequest(string $method, string $script, string $body, ContextInterface $context): AbstractRequest
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
