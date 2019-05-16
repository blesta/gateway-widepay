<?php
class WidepayResponse
{
    private $status;
    private $raw;
    private $response;
    private $errors;
    private $headers;

    /**
     * WidepayResponse constructor.
     *
     * @param array $apiResponse
     */
    public function __construct(array $apiResponse)
    {
        $this->raw = $apiResponse['content'];
        $this->response = json_decode($apiResponse['content']);
        $this->headers = $apiResponse['headers'];

        $this->status = '400';
        if (isset($this->headers[0])) {
            $status_parts = explode(' ', $this->headers[0]);
            if (isset($status_parts[1])) {
                $this->status = $status_parts[1];
            }
        }

        $this->errors =  [];
        if (isset($this->response->errors)) {
            foreach ($this->response->errors as $error) {
                $this->errors[] = $error->msg;
            }
        } elseif (isset($this->response->error)) {
            $this->errors = [$this->response->error];
        }
    }

    /**
     * Get the status of this response
     *
     * @return string The status of this response
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Get the raw data from this response
     *
     * @return string The raw data from this response
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Get the formatted data from this response
     *
     * @return string The formatted data from this response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Get any errors from this response
     *
     * @return array The errors from this response
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get the headers returned with this response
     *
     * @return array The headers returned with this response
     */
    public function headers()
    {
        return $this->headers;
    }
}
