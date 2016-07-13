<?php

namespace Nyholm\HttpClient;

use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Http\Client\Exception\TransferException;
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
    private $curl;

    /**
     * Make sure to destroy $curl.
     */
    public function __destruct()
    {
        curl_close($this->curl);
        $this->curl = null;
    }

    public function sendRequest(RequestInterface $request)
    {
        $curl = $this->createCurlHandle();
        $this->setOptionsFromRequest($curl, $request);

        $data = curl_exec($curl);

        if (false === $data) {
            $errorMsg = curl_error($curl);
            $errorNo = curl_errno($curl);

            throw new RequestException(sprintf('Error (%d): %s', $errorNo, $errorMsg), $request);
        }

        return $this->createResponse($curl, $data);
    }

    private function createCurlHandle()
    {
        if ($this->curl) {
            return $this->curl;
        }
        if (false === $curl = curl_init()) {
            throw new TransferException('Unable to create a new cURL handle');
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 0);
        curl_setopt($curl, CURLOPT_FAILONERROR, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        return $this->curl = $curl;
    }

    /**
     * Create a response object.
     *
     * @param resource $curl A cURL resource
     * @param string   $raw  The raw response string
     */
    private function createResponse($curl, $raw)
    {
        // fixes bug https://sourceforge.net/p/curl/bugs/1204/
        $version = curl_version();
        if (version_compare($version['version'], '7.30.0', '<')) {
            $pos = strlen($raw) - curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
        } else {
            $pos = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        }

        list($statsLine, $headers) = $this->parseHeaders(rtrim(substr($raw, 0, $pos)));
        $body = strlen($raw) > $pos ? substr($raw, $pos) : '';
        if (!preg_match('|^HTTP/([12].[01]) ([1-9][0-9][0-9]) (.*?)$|', $statsLine, $matches)) {
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
        $statusLine = null;
        $headers = array();
        foreach (preg_split('|(\\r?\\n)|', $raw) as $header) {
            if (!$statusLine) {
                $statusLine = $header;
                continue;
            }
            list($name, $value) = preg_split('|: |', $header);
            $headers[$name][] = $value;
        }

        return [$statusLine, $headers];
    }

    /**
     * Set CURL options from the Request.
     *
     * @param $curl
     * @param RequestInterface $request
     */
    private function setOptionsFromRequest($curl, RequestInterface $request)
    {
        $options = array(
            CURLOPT_HTTP_VERSION => $request->getProtocolVersion() === '1.0' ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_HTTPHEADER => $this->getHeaders($request),
            CURLOPT_POSTFIELDS => (string) $request->getBody(),
        );

        curl_setopt_array($curl, $options);
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
}
