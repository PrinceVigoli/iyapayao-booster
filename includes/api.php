<?php
declare(strict_types=1);

/**
 * BigSMMServer API wrapper.
 */
class SMMApi
{
    private string $api_url;
    private string $api_key;

    public function __construct(string $api_url, string $api_key)
    {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    // ----------------------------------------------------------------
    // Public methods
    // ----------------------------------------------------------------

    /** Get all services. */
    public function services(): array
    {
        return $this->request(['action' => 'services']);
    }

    /** Place a new order. */
    public function addOrder(
        int $service,
        string $link,
        int $quantity,
        ?int $runs = null,
        ?int $interval = null
    ): array {
        $params = [
            'action'   => 'add',
            'service'  => $service,
            'link'     => $link,
            'quantity' => $quantity,
        ];
        if ($runs !== null)     { $params['runs']     = $runs; }
        if ($interval !== null) { $params['interval'] = $interval; }
        return $this->request($params);
    }

    /** Get status of a single order. */
    public function orderStatus(int $order_id): array
    {
        return $this->request(['action' => 'status', 'order' => $order_id]);
    }

    /** Get status of multiple orders (comma-separated IDs or array). */
    public function multiOrderStatus(array $order_ids): array
    {
        return $this->request([
            'action' => 'status',
            'orders' => implode(',', $order_ids),
        ]);
    }

    /** Create a refill for a single order. */
    public function refill(int $order_id): array
    {
        return $this->request(['action' => 'refill', 'order' => $order_id]);
    }

    /** Create refills for multiple orders. */
    public function multiRefill(array $order_ids): array
    {
        return $this->request([
            'action' => 'refill',
            'orders' => implode(',', $order_ids),
        ]);
    }

    /** Get refill status for a single refill ID. */
    public function refillStatus(int $refill_id): array
    {
        return $this->request(['action' => 'refill_status', 'refill' => $refill_id]);
    }

    /** Get refill status for multiple refill IDs. */
    public function multiRefillStatus(array $refill_ids): array
    {
        return $this->request([
            'action'  => 'refill_status',
            'refills' => implode(',', $refill_ids),
        ]);
    }

    /** Cancel one or more orders. */
    public function cancel(array $order_ids): array
    {
        return $this->request([
            'action' => 'cancel',
            'orders' => implode(',', $order_ids),
        ]);
    }

    /** Get account balance. */
    public function balance(): array
    {
        return $this->request(['action' => 'balance']);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Send a POST request to the API and return the decoded JSON response.
     *
     * @param  array<string,mixed> $params
     * @return array<mixed>
     */
    private function request(array $params): array
    {
        $params['key'] = $this->api_key;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->api_url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'IyapayaoBooster/1.0',
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_err) {
            return ['error' => 'cURL error: ' . $curl_err];
        }

        if ($http_code !== 200) {
            return ['error' => 'HTTP error: ' . $http_code];
        }

        $decoded = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON decode error: ' . json_last_error_msg()];
        }

        return is_array($decoded) ? $decoded : ['error' => 'Unexpected API response'];
    }
}
