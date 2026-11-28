<?php

declare(strict_types=1);

namespace SimoneBianco\LaravelKeyRotator;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimoneBianco\LaravelKeyRotator\Models\ChatGptConnection;
use Throwable;

final class ChatGptOAuth
{
    private const ISSUER = 'https://auth.openai.com';

    private const RESOURCE = 'https://api.openai.com/v1';

    private const SCOPES = ['openid', 'profile', 'email', 'offline_access', 'resource.invoke', 'chatgpt.tokens.use.direct'];

    public function __construct(private readonly ChatGptHttp $http) {}

    public function begin(string $redirect, string $hostId, ?ChatGptConnection $connection = null, ?string $issuedClientId = null): string
    {
        $this->requireSharedLocks();
        if (! preg_match('~^http://127\.0\.0\.1:[1-9][0-9]{0,4}/auth/callback$~D', $redirect) || ! Str::isUuid($hostId)) {
            throw new ChatGptException('invalid_local_registration');
        }

        $state = Str::random(64);
        $nonce = Str::random(64);
        $verifier = Str::random(64);
        if ($issuedClientId !== null && ($connection !== null || ! $this->isIssuedClientId($issuedClientId))) {
            throw new ChatGptException('invalid_client_registration');
        }
        $client = $connection?->client_id ?? $issuedClientId ?? 'dynamic_agent_client';

        // An attempt is consumed once, even if the exchange fails.
        Cache::put($this->attemptKey($state), encrypt([
            'nonce' => $nonce, 'verifier' => $verifier, 'redirect' => $redirect,
            'host' => $hostId, 'connection' => $connection?->getKey(), 'client' => $client,
            'version' => $connection?->authorization_version,
        ]), now()->addMinutes(5));

        $parameters = [
            'client_id' => $client,
            'ext_agent_host_id' => 'urn:uuid:'.$hostId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'resource' => self::RESOURCE,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];

        if ($client === 'dynamic_agent_client') {
            $parameters['agent_name_hint'] = config('app.name');
        }

        return self::ISSUER.'/api/accounts/authorize?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function complete(string $expectedState, #[\SensitiveParameter] array $callback, ?callable $onInvalidGrant = null): ChatGptConnection
    {
        $this->requireSharedLocks();
        $state = $callback['state'] ?? null;
        if (! is_string($state) || ! hash_equals($expectedState, $state)) {
            throw new ChatGptException('invalid_state');
        }

        $key = $this->attemptKey($state);
        $attempt = Cache::lock($key.':consume', 5)->block(2, fn () => Cache::pull($key));
        if (! is_string($attempt)) {
            throw new ChatGptException('expired_or_consumed_state');
        }
        $pending = decrypt($attempt);

        if (isset($callback['error'])) {
            $reason = match ($callback['error']) {
                'access_denied' => 'authorization_declined',
                'invalid_request' => 'authorization_invalid_request',
                'invalid_scope' => 'authorization_invalid_scope',
                'server_error', 'temporarily_unavailable' => 'authorization_provider_unavailable',
                default => 'authorization_callback_error',
            };
            throw new ChatGptException($reason);
        }
        if (! is_string($callback['code'] ?? null) || $callback['code'] === '') {
            throw new ChatGptException('authorization_code_missing');
        }

        $client = $callback['client_id'] ?? $pending['client'];
        if (! $this->isIssuedClientId($client) || ($pending['client'] !== 'dynamic_agent_client' && $client !== $pending['client'])) {
            throw new ChatGptException('invalid_client_registration');
        }

        $response = $this->request(fn () => $this->http()->asForm()->post(self::ISSUER.'/api/accounts/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $client, 'code' => $callback['code'],
            'code_verifier' => $pending['verifier'], 'redirect_uri' => $pending['redirect'], 'resource' => self::RESOURCE,
        ]));
        if (! $response->successful()) {
            $reason = match ($response->json('error')) {
                'invalid_grant' => 'code_exchange_invalid_grant',
                'invalid_client' => 'code_exchange_invalid_client',
                'invalid_scope' => 'code_exchange_invalid_scope',
                default => 'code_exchange_rejected',
            };
            if ($reason === 'code_exchange_invalid_grant' && $onInvalidGrant !== null) {
                $onInvalidGrant($client);
            }
            $requestId = $response->header('x-request-id');
            throw new ChatGptException($reason, $response->status(),
                is_string($requestId) && preg_match('/^req_[A-Za-z0-9_-]{1,120}$/D', $requestId) ? $requestId : null);
        }
        $payload = $this->successfulPayload($response);
        $subject = $this->verifiedSubject($payload['id_token'] ?? null, $client, $pending['nonce']);
        $attributes = $this->tokenAttributes($payload);

        return Cache::lock('rotable:chatgpt:client:'.hash('sha256', $client), 45)->block(5, function () use ($pending, $client, $subject, $attributes): ChatGptConnection {
            $existing = ChatGptConnection::query()->where('client_id', $client)->first();
            if ($pending['connection'] !== null) {
                if ($existing?->getKey() !== $pending['connection'] || $existing->subject !== $subject) {
                    throw new ChatGptException('account_mismatch');
                }

                return $this->locked($existing->getKey(), function (ChatGptConnection $current) use ($attributes, $pending): ChatGptConnection {
                    if ($current->authorization_version !== $pending['version']) {
                        throw new ChatGptException('authorization_attempt_superseded');
                    }
                    $current->forceFill($attributes + [
                        'authorization_version' => $current->authorization_version + 1,
                        'token_version' => $current->token_version + 1,
                        'retry_after' => null,
                    ])->save();

                    return $current;
                });
            }
            if ($existing !== null) {
                throw new ChatGptException('registration_already_exists');
            }

            $connection = new ChatGptConnection;
            $connection->forceFill($attributes + [
                'client_id' => $client, 'subject' => $subject, 'host_id' => $pending['host'],
                'authorization_version' => 1, 'token_version' => 1,
            ])->save();

            return $connection;
        });
    }

    public function accessToken(int $id, #[\SensitiveParameter] ?string $rejectedToken = null): string
    {
        $this->requireSharedLocks();
        $connection = ChatGptConnection::query()->findOrFail($id);
        if ($this->valid($connection, $rejectedToken)) {
            return $connection->tokens['access'];
        }

        return $this->locked($id, function (ChatGptConnection $current) use ($rejectedToken): string {
            if ($this->valid($current, $rejectedToken)) {
                return $current->tokens['access'];
            }
            if ($current->status !== 'connected' || empty($current->tokens['refresh'])) {
                throw new ChatGptException($current->status === 'connected' ? 'reauth_required' : $current->status);
            }
            if ($current->refresh_retry_after?->isFuture()) {
                throw new ChatGptException('refresh_temporarily_unavailable');
            }

            try {
                $response = $this->request(fn () => $this->http()->asForm()->post(self::ISSUER.'/api/accounts/oauth/token', [
                    'grant_type' => 'refresh_token', 'client_id' => $current->client_id,
                    'refresh_token' => $current->tokens['refresh'], 'resource' => self::RESOURCE,
                ]));
            } catch (ChatGptException $exception) {
                $this->persistRefresh($current, ['refresh_retry_after' => now()->addSeconds(30)]);
                throw $exception;
            }

            if ($response->status() === 400 && $response->json('error') === 'invalid_grant') {
                $this->persistRefresh($current, ['status' => 'reauth_required', 'expires_at' => null]);
                throw new ChatGptException('reauth_required', 400);
            }

            if ($response->serverError() || $response->status() === 429) {
                $this->persistRefresh($current, ['refresh_retry_after' => now()->addSeconds(30)]);
            }
            $this->persistRefresh($current, $this->tokenAttributes($this->successfulPayload($response), $current));
            if ($current->status !== 'connected') {
                throw new ChatGptException($current->status);
            }

            return $current->tokens['access'];
        });
    }

    public function modelSlugs(int $id, #[\SensitiveParameter] ?string $token = null): array
    {
        $token ??= $this->accessToken($id);
        $key = 'rotable:chatgpt:models:'.$id.':'.hash('sha256', $token);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            return Cache::lock($key.':fetch', 20)->block(5, fn () => Cache::remember($key, now()->addMinutes(5), function () use ($token): array {
                $response = $this->request(fn () => $this->http()->withToken($token)->get(self::RESOURCE.'/models'));
                $models = $response->json('models');
                if (! $response->successful() || ! is_array($models)) {
                    $requestId = $response->header('x-request-id');
                    throw new ChatGptException('model_discovery_unavailable', $response->status(),
                        is_string($requestId) && preg_match('/^req_[A-Za-z0-9_-]{1,120}$/D', $requestId) ? $requestId : null);
                }

                return array_values(array_column(array_filter($models, fn ($model) => is_array($model)
                    && ($model['visibility'] ?? null) === 'list'
                    && is_string($model['slug'] ?? null)), 'slug'));
            }));
        } catch (LockTimeoutException) {
            throw new ChatGptException('model_discovery_busy');
        }
    }

    public function disconnect(int $id): void
    {
        $this->requireSharedLocks();
        $this->locked($id, function (ChatGptConnection $current): void {
            // Stop local use even when revocation cannot be confirmed.
            $current->forceFill([
                'status' => 'disconnected', 'expires_at' => null, 'retry_after' => null,
                'refresh_retry_after' => null,
                'authorization_version' => $current->authorization_version + 1,
                'token_version' => $current->token_version + 1,
            ])->save();
            $token = $current->tokens['refresh'] ?? null;
            if (is_string($token)) {
                $discovery = $this->successfulPayload($this->request(fn () => $this->http()->get(self::ISSUER.'/.well-known/openid-configuration')));
                $endpoint = $discovery['revocation_endpoint'] ?? null;
                if (! is_string($endpoint) || ! preg_match('~^https://auth\.openai\.com/[a-zA-Z0-9/_.-]+$~D', $endpoint)) {
                    throw new ChatGptException('revocation_endpoint_unavailable');
                }
                $response = $this->request(fn () => $this->http()->asForm()->post($endpoint, [
                    'token' => $token, 'token_type_hint' => 'refresh_token', 'client_id' => $current->client_id,
                ]));
                if ($response->status() !== 200) {
                    throw new ChatGptException('revocation_unconfirmed', $response->status());
                }
            }
            $current->forceFill(['tokens' => null, 'scopes' => null])->save();
        });
    }

    private function persistRefresh(ChatGptConnection $connection, #[\SensitiveParameter] array $attributes): void
    {
        $version = $connection->token_version;
        $connection->forceFill($attributes + ['token_version' => $version + 1]);

        if (ChatGptConnection::query()->whereKey($connection->getKey())->where('token_version', $version)
            ->update($connection->getDirty()) !== 1) {
            throw new ChatGptException('refresh_superseded');
        }
    }

    private function locked(int $id, callable $callback): mixed
    {
        try {
            return Cache::lock('rotable:chatgpt:connection:'.$id, 45)->block(5,
                fn () => $callback(ChatGptConnection::query()->findOrFail($id)));
        } catch (LockTimeoutException) {
            throw new ChatGptException('connection_busy');
        }
    }

    private function requireSharedLocks(): void
    {
        $driver = config('cache.stores.'.config('cache.default').'.driver');
        if (! app()->environment('testing') && in_array($driver, ['array', 'null', null], true)) {
            throw new ChatGptException('shared_cache_required');
        }
    }

    private function valid(ChatGptConnection $connection, #[\SensitiveParameter] ?string $rejected): bool
    {
        $token = $connection->tokens['access'] ?? null;

        return $connection->status === 'connected' && $connection->expires_at?->gt(now()->addSeconds(60))
            && is_string($token) && $token !== $rejected;
    }

    private function tokenAttributes(#[\SensitiveParameter] array $payload, ?ChatGptConnection $current = null): array
    {
        $access = $payload['access_token'] ?? null;
        $refresh = $payload['refresh_token'] ?? $current?->tokens['refresh'] ?? null;
        $expiry = $payload['expires_in'] ?? null;
        $scope = $payload['scope'] ?? null;
        $type = $payload['token_type'] ?? null;
        if (! is_string($access) || trim($access) === '' || ! is_string($refresh) || trim($refresh) === ''
            || ! is_int($expiry) || $expiry < 1 || ! is_string($type) || strcasecmp($type, 'Bearer') !== 0
            || ($scope !== null && ! is_string($scope))) {
            throw new ChatGptException('malformed_token_response');
        }

        $scopes = is_string($scope) ? preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) : ($current?->scopes ?? []);

        return [
            'tokens' => ['access' => $access, 'refresh' => $refresh],
            'scopes' => $scopes,
            'expires_at' => now()->addSeconds($expiry),
            'refresh_retry_after' => null,
            'status' => array_diff(['resource.invoke', 'chatgpt.tokens.use.direct'], $scopes) === [] ? 'connected' : 'permission_required',
        ];
    }

    private function verifiedSubject(#[\SensitiveParameter] mixed $idToken, string $client, string $nonce): string
    {
        if (! is_string($idToken)) {
            throw new ChatGptException('missing_identity_token');
        }

        $response = $this->request(fn () => $this->http()->get(self::ISSUER.'/.well-known/jwks.json'));
        if (! $response->successful() || ! is_array($jwks = $response->json()) || ! is_array($jwks['keys'] ?? null)) {
            throw new ChatGptException('identity_keys_unavailable', $response->status());
        }
        try {
            $jwks['keys'] = array_values(array_filter($jwks['keys'] ?? [], fn ($key) => is_array($key)
                && ($key['kty'] ?? null) === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256'
                && ($key['use'] ?? 'sig') === 'sig'));
            $claims = JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));
        } catch (Throwable) {
            throw new ChatGptException('identity_verification_failed');
        }

        $audiences = (array) ($claims->aud ?? []);
        if (($claims->iss ?? null) !== self::ISSUER) {
            throw new ChatGptException('identity_issuer_mismatch');
        }
        if (! in_array($client, $audiences, true)
            || ((count($audiences) > 1 || isset($claims->azp)) && ($claims->azp ?? null) !== $client)) {
            throw new ChatGptException('identity_audience_mismatch');
        }
        if (! is_int($claims->exp ?? null) || $claims->exp <= now()->timestamp
            || ! is_int($claims->iat ?? null) || $claims->iat > now()->timestamp) {
            throw new ChatGptException('identity_time_invalid');
        }
        if (! is_string($claims->nonce ?? null) || ! hash_equals($nonce, $claims->nonce)) {
            throw new ChatGptException('identity_nonce_mismatch');
        }
        if (! is_string($claims->sub ?? null) || $claims->sub === '') {
            throw new ChatGptException('identity_subject_missing');
        }

        return $claims->sub;
    }

    private function attemptKey(string $state): string
    {
        return 'rotable:chatgpt:attempt:'.hash('sha256', $state);
    }

    private function isIssuedClientId(mixed $value): bool
    {
        return is_string($value) && $value !== 'dynamic_agent_client' && preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $value) === 1;
    }

    private function http(): PendingRequest
    {
        return $this->http->acceptJson()->connectTimeout(4)->timeout(12)->withoutRedirecting();
    }

    private function request(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException) {
            throw new ChatGptException('authentication_transport_failure');
        }
    }

    private function successfulPayload(Response $response): array
    {
        if (! $response->successful() || ! is_array($data = $response->json())) {
            throw new ChatGptException('authentication_response_rejected', $response->status());
        }

        return $data;
    }
}
