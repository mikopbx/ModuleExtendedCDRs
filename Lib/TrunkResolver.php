<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class TrunkResolver
{
    /** @var array<string,array{name:string,id:string,host:string}> */
    private array $byId = [];
    /** @var array<string,int> */
    private array $hostProviderCount = [];
    /** @var array<string,array<string,array<int,array{name:string,id:string,host:string}>>> */
    private array $byHostAndUsername = [];

    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $provider = (array)$provider;
            $id = (string)($provider['uniqid'] ?? '');
            $name = (string)($provider['description'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $host = self::normalizeHost((string)($provider['host'] ?? ''));
            $candidate = ['name' => $name, 'id' => $id, 'host' => $host];
            $this->byId[$id] = $candidate;
            if ($host === '') {
                continue;
            }
            $this->hostProviderCount[$host] = ($this->hostProviderCount[$host] ?? 0) + 1;
            $username = self::normalizeNumber((string)($provider['username'] ?? ''));
            if ($username !== '') {
                $this->byHostAndUsername[$host][$username][] = $candidate;
            }
        }
    }

    /** @return array{name:string,id:string,status:string,source:string,candidates:array} */
    public function resolve(array $record, string $callType): array
    {
        $technical = (string)($record['line'] ?? '');
        if (isset($this->byId[$technical])) {
            $lineProvider = $this->byId[$technical];
            $host = $lineProvider['host'];
            $isIncoming = in_array($callType, ['incoming', '2', '3'], true);
            if ($isIncoming && $host !== '' && ($this->hostProviderCount[$host] ?? 0) > 1) {
                $did = self::normalizeNumber((string)($record['did'] ?? ''));
                $candidates = $did === '' ? [] : ($this->byHostAndUsername[$host][$did] ?? []);
                if (count($candidates) === 1) {
                    return $this->resolved($candidates[0], 'did_username');
                }
            }
            return $this->resolved($lineProvider, 'line_id');
        }

        return [
            'name' => $technical,
            'id' => $technical,
            'status' => 'unresolved',
            'source' => 'technical',
            'candidates' => [],
        ];
    }

    private function resolved(array $candidate, string $source): array
    {
        return [
            'name' => $candidate['name'],
            'id' => $candidate['id'],
            'status' => 'resolved',
            'source' => $source,
            'candidates' => [],
        ];
    }

    private static function normalizeNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?: '';
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(trim($host));
    }
}
