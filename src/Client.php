<?php

namespace Nyholm\HttpClient;

use Exception;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Psr\Http\Message\RequestInterface;

/**
 * A minimalistic HTTP client
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Client implements HttpClient
{
    const CURL_DEFAULT_OPTIONS = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_FAILONERROR => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 10,
    ];

    private $curl;

    public function __construct()
    {
        $this->curl = curl_init();

        if (false === $this->curl) {
            throw new Exception('Unable to create a new cURL handle');
        }

        curl_setopt_array($this->curl, self::CURL_DEFAULT_OPTIONS);
    }

    public function sendRequest(RequestInterface $request)
    {
        if (false === $this->setOptionsFromRequest($request)) {
            throw new RequestException('Not a valid request.', $request);
        }

        if (false === $data = curl_exec($this->curl)) {
            throw new RequestException(
                sprintf('Error (%d): %s', curl_errno($this->curl), curl_error($this->curl)),
                $request
            );
        }

        return $this->createResponse($data);
    }

    /**
     * Create a response object.
     *
     * @param string $raw The raw response string
     */
    private function createResponse($raw)
    {
        // fixes bug https://sourceforge.net/p/curl/bugs/1204/
        if (version_compare(curl_version()['version'], '7.30.0', '<')) {
            $pos = strlen($raw) - curl_getinfo($this->curl, CURLINFO_SIZE_DOWNLOAD);
        } else {
            $pos = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        }

        list($statusLine, $headers) = $this->parseHeaders(rtrim(substr($raw, 0, $pos)));
        $body = strlen($raw) > $pos ? substr($raw, $pos) : '';

        if (!preg_match('|^HTTP/([12].[01]) ([1-9][0-9][0-9]) (.*?)$|', $statusLine, $matches)) {
            throw new HttpException('Not a HTTP response');
        }

        return MessageFactoryDiscovery::find()->createResponse((int) $matches[2], $matches[3], $headers, $body, $matches[1]);
    }

    /**
     * Parse raw data for headers.
     *
     * @param string $raw Raw response byt no body
     *
     * @return array with status line and the headers.
     */
    private function parseHeaders($raw)
    {
        $rawHeaders = preg_split('|(\\r?\\n)|', $raw);
        $statusLine = array_shift($rawHeaders);

        return array_reduce($rawHeaders, function($parsedHeaders, $header) {
            list($name, $value) = preg_split('|: |', $header);
            $parsedHeaders[1][$name][] = $value;

            return $parsedHeaders;
        }, [$statusLine, []]);
    }

    /**
     * Set CURL options from the Request.
     *
     * @param RequestInterface $request
     * @return bool
     */
    private function setOptionsFromRequest(RequestInterface $request)
    {
        return curl_setopt_array(
            $this->curl,
            [
                CURLOPT_HTTP_VERSION => $request->getProtocolVersion() === '1.0' ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $request->getMethod(),
                CURLOPT_URL => (string) $request->getUri(),
                CURLOPT_HTTPHEADER => $this->getHeaders($request),
                CURLOPT_POSTFIELDS => (string) $request->getBody(),
            ]
        );
    }

    /**
     * Get headers from a PSR-7 Request to Curl format.
     *
     * @param RequestInterface $request
     *
     * @return array
     */
    private function getHeaders(RequestInterface $request)
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $array) {
            foreach ($array as $header) {
                $headers[] = sprintf('%s: %s', $name, $header);
            }
        }

        return $headers;
    }

    /**
     * Make sure to destroy $curl.
     */
    public function __destruct()
    {
        curl_close($this->curl);
    }
}
