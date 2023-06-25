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

use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Requests\AbstractRequest;
use hollodotme\FastCGI\Requests\DeleteRequest;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PatchRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\Requests\PutRequest;
use hollodotme\FastCGI\SocketConnections;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\MediaType;

/**
 * FcgiProcessor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class FcgiProcessor extends ActionAbstract
{
    public function getName(): string
    {
        return 'FastCGI-Processor';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $host = $configuration->get('host');
        if (empty($host)) {
            throw new ConfigurationException('No host configured');
        }

        $script = $configuration->get('script');
        if (empty($script)) {
            throw new ConfigurationException('No script configured');
        }

        $port = $configuration->get('port');
        if (empty($port)) {
            $connection = new SocketConnections\UnixDomainSocket($host);
        } else {
            $connection = new SocketConnections\NetworkSocket($host, $port);
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

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newInput('host', 'Host', 'text', 'Hostname or path to a socket'));
        $builder->add($elementFactory->newInput('port', 'Port', 'text', 'The port of the server if a hostname was provided'));
        $builder->add($elementFactory->newInput('script', 'Script', 'text', 'The script which should be executed by the server'));
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
        $request->setCustomVar('OPERATION_ID', $context->getOperationId());
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
