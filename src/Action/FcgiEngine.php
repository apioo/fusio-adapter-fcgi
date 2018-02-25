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
use Hoa\Fastcgi\Responder;
use Hoa\Socket\Client;
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
    protected $socket;

    /**
     * @var string
     */
    protected $script;

    public function __construct($socket = null, $script = null)
    {
        $this->socket = $socket;
        $this->script = $script;
    }

    public function setSocket($socket)
    {
        $this->socket = $socket;
    }

    public function setScript($script)
    {
        $this->script = $script;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $headers = [
            'REQUEST_METHOD'  => $request->getMethod(),
            'REQUEST_URI'     => '/',
            'SCRIPT_FILENAME' => $this->script,
            'CONTENT_TYPE'    => 'application/json',
            'REMOTE_ADDR'     => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
            'REMOTE_USER'     => $context->getUser()->getName(),

            'ROUTE_ID'        => $context->getRouteId(),
            'USER_ANONYMOUS'  => $context->getUser()->isAnonymous() ? '1' : '0',
            'USER_ID'         => $context->getUser()->getId(),
            'APP_ID'          => $context->getApp()->getId(),
            'APP_KEY'         => $context->getApp()->getAppKey(),
        ];

        if (!$context->getUser()->isAnonymous()) {
            $headers['AUTH_TYPE'] = 'Bearer';
        }

        $fastcgi = new Responder(new Client($this->socket));
        $fastcgi->send($headers, json_encode($request->getBody()));

        $headers = $fastcgi->getResponseHeaders();
        $content = $fastcgi->getResponseContent();

        if (isset($headers['status'])) {
            $parts = explode(' ', $headers['status']);
            $code  = intval($parts[0]);
        } else {
            $code  = 200;
        }

        $contentType = isset($headers['content-type']) ? $headers['content-type'] : null;
        if ($this->isJson($contentType)) {
            $body = json_decode($content);
        } else {
            $body = $content;
        }

        return $this->response->build(
            $code,
            $headers,
            $body
        );
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
}
