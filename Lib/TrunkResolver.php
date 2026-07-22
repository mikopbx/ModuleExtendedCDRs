<?php

namespace Modules\ModuleExtendedCDRs\Lib;

final class TrunkResolver
{
    /** @var array<string,array{name:string,id:string}> */
    private array $byId = [];
    /** @var array<string,array<int,array{name:string,id:string}>> */
    private array $byUsername = [];

    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $provider = (array)$provider;
            $id = (string)($provider['uniqid'] ?? '');
            $name = (string)($provider['description'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            $candidate = ['name' => $name, 'id' => $id];
            $this->byId[$id] = $candidate;
            $username = self::normalizeNumber((string)($provider['username'] ?? ''));
            if ($username !== '') {
                $this->byUsername[$username][] = $candidate;
            }
        }
    }

    /** @return array{name:string,id:string,status:string,source:string,candidates:array} */
    public function resolve(array $record, string $callType): array
    {
        $technical = (string)($record['line'] ?? '');
        if (isset($this->byId[$technical])) {
            return $this->resolved($this->byId[$technical], 'line_id');
        }

        if ($callType === 'incoming' || $callType === '2') {
            $did = self::normalizeNumber((string)($record['did'] ?? ''));
            $candidates = $did === '' ? [] : ($this->byUsername[$did] ?? []);
            if (count($candidates) === 1) {
                return $this->resolved($candidates[0], 'did_username');
            }
            if (count($candidates) > 1) {
                return [
                    'name' => $technical,
                    'id' => $technical,
                    'status' => 'ambiguous',
                    'source' => 'did_username',
                    'candidates' => array_column($candidates, 'id'),
                ];
            }
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
}
