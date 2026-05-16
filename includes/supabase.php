<?php
class SupabaseQuery {
    private string $url;
    private string $key;
    private string $table;
    private array  $params  = [];
    private array  $headers = [];

    function __construct(string $url, string $key, string $table) {
        $this->url   = $url;
        $this->key   = $key;
        $this->table = $table;
        $this->headers = [
            "apikey: {$key}",
            "Authorization: Bearer {$key}",
            "Content-Type: application/json",
        ];
    }

    function select(string $cols = '*'): self { $this->params['select'] = $cols; return $this; }
    function eq(string $c, mixed $v): self    { $this->params[$c] = "eq.{$v}";    return $this; }
    function neq(string $c, mixed $v): self   { $this->params[$c] = "neq.{$v}";   return $this; }
    function gt(string $c, mixed $v): self    { $this->params[$c] = "gt.{$v}";    return $this; }
    function in(string $c, array $v): self    { $this->params[$c] = 'in.(' . implode(',', $v) . ')'; return $this; }
    function ilike(string $c, string $v): self { $this->params[$c] = "ilike.{$v}"; return $this; }
    function order(string $c, bool $asc = true): self { $this->params['order'] = $c . ($asc ? '.asc' : '.desc'); return $this; }
    function limit(int $n): self   { $this->params['limit']  = $n; return $this; }
    function offset(int $n): self  { $this->params['offset'] = $n; return $this; }
    function or(string $cond): self { $this->params['or'] = "({$cond})"; return $this; }

    private function buildUrl(): string {
        return "{$this->url}/rest/v1/{$this->table}" . ($this->params ? '?' . http_build_query($this->params) : '');
    }

    private function exec(string $method, mixed $body = null, array $extra = []): mixed {
        $ch = curl_init($this->buildUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge($this->headers, $extra),
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $res = curl_exec($ch);
        return json_decode($res, true);
    }

    function get(): array {
        $r = $this->exec('GET');
        return is_array($r) ? $r : [];
    }

    function single(): ?array {
        $r = $this->limit(1)->get();
        return $r[0] ?? null;
    }

    function count(): int {
        $saved = $this->params;
        $this->params['select'] = 'count';
        $r = $this->get();
        $this->params = $saved;
        return (int)($r[0]['count'] ?? 0);
    }

    function sum(string $col): float {
        $saved = $this->params;
        $this->params['select'] = "sum({$col})";
        $r = $this->get();
        $this->params = $saved;
        return (float)($r[0]['sum'] ?? 0);
    }

    function insert(array $data): ?array {
        $ch = curl_init("{$this->url}/rest/v1/{$this->table}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array_merge($this->headers, ['Prefer: return=representation']),
        ]);
        $r = json_decode(curl_exec($ch), true);
                return is_array($r) ? ($r[0] ?? null) : null;
    }

    function update(array $data): array {
        $ch = curl_init($this->buildUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array_merge($this->headers, ['Prefer: return=representation']),
        ]);
        $r = json_decode(curl_exec($ch), true);
                return is_array($r) ? $r : [];
    }

    function delete(): void {
        $ch = curl_init($this->buildUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => $this->headers,
        ]);
        curl_exec($ch);
            }
}

class Supabase {
    public string $url;
    public string $key;

    function __construct(string $url, string $key) {
        $this->url = rtrim($url, '/');
        $this->key = $key;
    }

    function from(string $table): SupabaseQuery {
        return new SupabaseQuery($this->url, $this->key, $table);
    }

    function rpc(string $fn, array $params = []): mixed {
        $ch = curl_init("{$this->url}/rest/v1/rpc/{$fn}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                "apikey: {$this->key}",
                "Authorization: Bearer {$this->key}",
                "Content-Type: application/json",
            ],
        ]);
        $r = curl_exec($ch);
                return json_decode($r, true);
    }
}
