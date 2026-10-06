<?php

namespace App\Mock\Postman;

use App\Mock\Manifest\Manifest;
use App\Mock\Unverified;

/**
 * Adds repeatable checks to the sanitized collection (G5.1, G5.2) WITHOUT changing any
 * request: only `event` scripts and one extra folder are added. PostmanRequestsIntactTest
 * proves the requests stay byte-identical.
 *
 * - collection pre-request: fills `<uuid>` path variables and query placeholders from the
 *   selected environment, drops optional placeholder filters, turns body placeholders into
 *   valid values; and ON THE MOCK ONLY (`resetBeforeEach` = true, never set in production)
 *   resets to the default scenario before each request, so the collection's own order
 *   (a delete before a get) cannot break later requests.
 * - collection test: error envelope on errors, list envelope on lists, valid JSON.
 * - per request: the documented status, content type and required top-level fields.
 * - a read-only folder: the GET requests that need nothing but `siteId`, safe to run
 *   against production with owner approval (D4-b).
 */
final class PostmanTests
{
    public const READ_ONLY_FOLDER = 'Read-only checks (safe for production)';

    public function __construct(private readonly Manifest $manifest) {}

    public function apply(array $collection): array
    {
        $collection['item'] = array_values(array_filter($collection['item'], fn ($g) => $g['name'] !== self::READ_ONLY_FOLDER));
        $collection['event'] = [
            ['listen' => 'prerequest', 'script' => ['type' => 'text/javascript', 'exec' => self::lines(self::PRE_REQUEST)]],
            ['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => self::lines(str_replace('__ENVELOPE__', json_encode(Unverified::ERROR_ENVELOPE_FIELDS), self::COLLECTION_TEST))]],
        ];

        $entries = [];
        foreach ($this->manifest->entries() as $entry) {
            $entries[$entry['group']."\0".$entry['name']] = $entry;
        }

        $readOnly = [];
        foreach ($collection['item'] as &$group) {
            foreach ($group['item'] as &$item) {
                $entry = $entries[$group['name']."\0".$item['name']] ?? null;
                if ($entry === null) {
                    continue;
                }
                $item['event'] = [['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $this->itemTest($entry)]]];
                if ($entry['method'] === 'GET' && array_diff($entry['pathParams'], ['siteId', 'consoleId']) === []) {
                    $readOnly[] = $item;
                }
            }
        }
        unset($group, $item);

        $collection['item'][] = [
            'name' => self::READ_ONLY_FOLDER,
            'description' => 'GET requests needing only siteId. Same assertions as above, so any difference between the mock and the real API shows as a named red test. Run against unifi-production ONLY with owner approval (D4-b).',
            'item' => $readOnly,
        ];

        return $collection;
    }

    /** @return list<string> */
    private function itemTest(array $entry): array
    {
        $required = $entry['response']['fields']['']['required'] ?? [];
        $item = $entry['response']['fields']['data[]']['required'] ?? [];
        $lines = [
            "pm.test('status {$entry['status']}', () => pm.response.to.have.status({$entry['status']}));",
            "pm.test('content type {$entry['contentType']}', () => pm.expect(pm.response.headers.get('Content-Type') || '').to.include('{$entry['contentType']}'));",
        ];
        if ($required !== []) {
            $lines[] = 'if (pm.response.code < 400) pm.test(\'required fields\', () => { const b = pm.response.json(); '.json_encode($required).'.forEach(k => pm.expect(b, k).to.have.property(k)); });';
        }
        if ($item !== []) {
            $lines[] = 'if (pm.response.code < 400) pm.test(\'required item fields\', () => { const d = pm.response.json().data; if (d.length) '.json_encode($item).'.forEach(k => pm.expect(d[0], k).to.have.property(k)); });';
        }

        return $lines;
    }

    /** @return list<string> */
    private static function lines(string $js): array
    {
        return explode("\n", trim($js));
    }

    private const PRE_REQUEST = <<<'JS'
// Added by `php artisan mock:postman-tests`. It changes no stored request: it fills
// placeholders at send time from the selected environment.
const isPlaceholder = (v) => typeof v === 'string' && /^<.+>$/.test(v);
pm.request.url.variables.each((v) => {
  const value = pm.variables.get(v.key);
  if (isPlaceholder(v.value) && value) v.value = value;
});
pm.request.url.query.each((q) => {
  if (q.disabled || !isPlaceholder(q.value)) return;
  const description = q.description ? String(q.description.content || q.description) : '';
  const value = q.key === 'filter' ? (description.includes('Required') ? pm.variables.get('requiredFilter') : null) : pm.variables.get(q.key);
  if (value) q.value = value; else q.disabled = true;
});
if (pm.request.body && pm.request.body.mode === 'raw' && pm.request.body.raw && pm.request.body.raw.includes('<')) {
  const fill = (v, key) => {
    if (Array.isArray(v)) return v.map((x) => fill(x, key));
    if (v && typeof v === 'object') { const o = {}; Object.keys(v).forEach((k) => { o[k] = fill(v[k], k); }); return o; }
    if (!isPlaceholder(v)) return v;
    switch (v.slice(1, -1)) {
      case 'boolean': return true;
      case 'integer': case 'long': return 60;
      case 'number': case 'double': return 1.5;
      case 'uuid': return pm.variables.get(key) || '00000000-0000-4000-8000-000000000000';
      case 'string': return pm.variables.get(key) || 'example';
      default: return null;
    }
  };
  try { pm.request.body.raw = JSON.stringify(fill(JSON.parse(pm.request.body.raw), null)); } catch (e) { /* not JSON: leave it */ }
}
// Mock only: start every request from the default scenario (ids are deterministic).
if (pm.variables.get('resetBeforeEach') === 'true' && pm.variables.get('controlBase')) {
  const resolved = () => {
    let url = pm.variables.replaceIn(pm.request.url.toString());
    pm.request.url.variables.each((v) => { url = url.replace(':' + v.key, pm.variables.replaceIn(v.value)); });
    return url;
  };
  pm.sendRequest({ url: pm.variables.get('controlBase') + '/reset?seed=default', method: 'POST' }, () => {
    if (pm.request.method === 'PUT' && /\/ordering(\?|$)/.test(resolved())) {
      pm.sendRequest({ url: resolved(), method: 'GET', header: { 'X-API-KEY': pm.variables.get('apiKey') } }, (err, res) => {
        if (!err) pm.request.body.raw = JSON.stringify(res.json());
      });
    }
  });
}
JS;

    private const COLLECTION_TEST = <<<'JS'
// Added by `php artisan mock:postman-tests`: shape checks shared by every request.
const contentType = pm.response.headers.get('Content-Type') || '';
if (pm.response.code >= 400) {
  pm.test('error envelope (UNVERIFIED, G1)', () => {
    const body = pm.response.json();
    __ENVELOPE__.forEach((k) => pm.expect(body, k).to.have.property(k));
  });
} else if (contentType.includes('application/json')) {
  pm.test('valid JSON', () => pm.response.json());
  if (pm.request.url.query.has('offset')) {
    pm.test('list envelope', () => {
      const body = pm.response.json();
      ['offset', 'limit', 'count', 'totalCount', 'data'].forEach((k) => pm.expect(body, k).to.have.property(k));
      pm.expect(body.count).to.eql(body.data.length);
    });
  }
}
JS;
}
