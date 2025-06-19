<?php

class PromptSuggestionService {
    /**
     * @var string
     */
    private $api_key;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $model;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $max_retries;

    /**
     * @var array
     */
    private $allowed_hosts;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_key        = trim( get_option( 'meta_ai_optimizer_api_key', '' ) );
        $this->endpoint       = esc_url_raw( get_option( 'meta_ai_optimizer_api_endpoint', 'https://api.openai.com/v1/chat/completions' ) );
        $this->model          = sanitize_text_field( get_option( 'meta_ai_optimizer_model', 'gpt-3.5-turbo' ) );
        $this->timeout        = absint( get_option( 'maio_timeout', 30 ) );
        $this->max_retries    = 3;
        $this->allowed_hosts  = apply_filters(
            'maio_allowed_api_hosts',
            array(
                'api.openai.com',
                'openai.azure.com',
            )
        );
    }

    /**
     * Generate optimized title and meta description.
     *
     * @param string $title
     * @param string $meta
     * @return string|WP_Error
     */
    public function generate( $title, $meta ) {
        $title = sanitize_text_field( $title );
        $meta  = sanitize_textarea_field( $meta );

        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'API key not set.', 'meta-ai-optimizer' ) );
        }

        $prompt = sprintf(
            "Improve the following SEO title and meta description.\n\nTitle: %s\nMeta Description: %s",
            $title,
            $meta
        );

        return $this->call_api( $prompt );
    }

    /**
     * Call OpenAI/OpenRouter API with retry and endpoint validation.
     *
     * @param string $prompt
     * @return string|WP_Error
     */
    private function call_api( $prompt ) {
        $parsed = wp_parse_url( $this->endpoint );
        $host   = isset( $parsed['host'] ) ? $parsed['host'] : '';

        if ( empty( $host ) || ! in_array( $host, $this->allowed_hosts, true ) ) {
            return new WP_Error(
                'invalid_endpoint',
                __( 'Invalid API endpoint.', 'meta-ai-optimizer' ),
                array( 'host' => $host )
            );
        }

        $payload = array(
            'model'    => $this->model,
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are an expert in SEO and content optimization. Provide an improved SEO-friendly title and meta description based on the user input.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $args = array(
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body'        => wp_json_encode( $payload ),
            'timeout'     => $this->timeout,
            'data_format' => 'body',
        );

        $attempt = 0;
        $response = null;
        $last_error = null;

        while ( $attempt <= $this->max_retries ) {
            if ( $attempt > 0 ) {
                $backoff = pow( 2, $attempt );
                sleep( $backoff );
            }

            $response = wp_remote_post( $this->endpoint, $args );

            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                $attempt++;
                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );

            // Retry on rate limit or server errors.
            if ( in_array( $status_code, array( 429, 500, 502, 503, 504 ), true ) && $attempt < $this->max_retries ) {
                $attempt++;
                continue;
            }

            if ( 200 !== $status_code ) {
                $body = wp_remote_retrieve_body( $response );
                return new WP_Error(
                    'api_error',
                    __( 'API request failed.', 'meta-ai-optimizer' ),
                    array(
                        'status' => $status_code,
                        'body'   => $body,
                    )
                );
            }

            // Successful response.
            break;
        }

        if ( is_wp_error( $last_error ) ) {
            return $last_error;
        }

        if ( ! $response || is_wp_error( $response ) ) {
            return new WP_Error(
                'request_failed',
                __( 'Request failed after retries.', 'meta-ai-optimizer' )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'json_error',
                __( 'Invalid JSON response.', 'meta-ai-optimizer' ),
                array( 'response' => $body )
            );
        }

        if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return new WP_Error(
            'no_content',
            __( 'No content returned from API.', 'meta-ai-optimizer' ),
            array( 'response' => $data )
        );
    }
}