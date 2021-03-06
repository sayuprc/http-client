<?php

namespace HttpClient;

class Client
{
    private $ch = null;

    public function __construct()
    {
        $this->init();
    }

    public function __destruct()
    {
        $this->close();
    }

    private function init(): void
    {
        $this->close();

        $this->ch = curl_init();
    }

    private function close(): void
    {
        if ($this->ch !== null) {
            curl_close($this->ch);
            $this->ch = null;
        }
    }

    public function get(string $url, array $options = []): HttpResponse
    {
        return $this->request($url, 'get', $options);
    }

    public function post(string $url, array $options = []): HttpResponse
    {
        return $this->request($url, 'post', $options);
    }

    /**
     * @throws ClientException
     */
    private function request(string $url, string $method, array $options = []): HttpResponse
    {
        if ($this->ch === null) {
            $this->init();
        } else {
            $this->reset();
        }

        $withHeader = isset($options['with_header']) ? true : false;

        $curlOptions = [
            CURLOPT_URL => $this->createUrl($url, $options['query'] ?? []),
            CURLOPT_POST => strtoupper($method) === 'POST' ? true : false,
            CURLOPT_HTTPHEADER => $options['headers'] ?? [],
            CURLOPT_POSTFIELDS => $this->createRequestBody($options),
            CURLOPT_HEADER => $withHeader,
            CURLOPT_RETURNTRANSFER => true,
        ];

        curl_setopt_array($this->ch, $curlOptions);

        $response = curl_exec($this->ch);
        $info = curl_getinfo($this->ch);
        $errorNo = curl_errno($this->ch);
        $errorMessage = curl_error($this->ch);

        if ($errorNo !== 0) {
            throw new ClientException($errorMessage, $errorNo, curl_strerror($errorNo), $response, $info);
        }

        if ($withHeader) {
            $headerSize = $info['header_size'];
            $headers = array_filter(
                explode("\r\n", substr($response, 0, $headerSize)),
                fn ($val) => $val
            );
            $body = substr($response, $headerSize);
        } else {
            $headers = [];
            $body = $response;
        }

        return new HttpResponse($info['http_code'], $body, $headers);
    }

    private function reset(): void
    {
        if ($this->ch !== null) {
            curl_reset($this->ch);
        }
    }

    private function createUrl(string $baseUrl, array $parameters = []): string
    {
        $url = $baseUrl;
        $queries = '';

        if (!empty($parameters)) {
            $queries = http_build_query($parameters);

            $url .= '?' . $queries;
        }

        return $url;
    }

    private function createRequestBody(array $body = []): string|array
    {
        if (isset($body['json'])) {
            return json_encode($body['json']);
        } elseif (isset($body['body'])) {
            return $body['body'];
        } else {
            return '';
        }
    }
}
