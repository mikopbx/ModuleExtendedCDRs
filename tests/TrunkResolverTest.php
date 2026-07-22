<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Lib/TrunkResolver.php';

use Modules\ModuleExtendedCDRs\Lib\TrunkResolver;

function trunkAssert($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$resolver = new TrunkResolver([
    ['uniqid' => 'SIP-A', 'username' => '+7 (495) 111-22-33', 'description' => 'Provider A'],
    ['uniqid' => 'SIP-B', 'username' => '74952223344', 'description' => 'Provider B'],
]);

$line = $resolver->resolve(['line' => 'SIP-A', 'did' => '74952223344'], 'incoming');
trunkAssert('Provider A', $line['name'], 'stable line id has highest priority');
trunkAssert('line_id', $line['source'], 'line id evidence source');

$did = $resolver->resolve(['line' => 'unknown-peer', 'did' => '7 495 222-33-44'], 'incoming');
trunkAssert('Provider B', $did['name'], 'incoming unique DID resolves provider');
trunkAssert('did_username', $did['source'], 'DID evidence source');
$storedType = $resolver->resolve(['line' => 'unknown-peer', 'did' => '74952223344'], '2');
trunkAssert('Provider B', $storedType['name'], 'stored incoming call type resolves DID');

$outgoing = $resolver->resolve(['line' => 'unknown-peer', 'did' => '74952223344'], 'outgoing');
trunkAssert('unresolved', $outgoing['status'], 'outgoing call does not use DID');

$ambiguous = new TrunkResolver([
    ['uniqid' => 'SIP-A', 'username' => '100500', 'description' => 'Provider A'],
    ['uniqid' => 'SIP-B', 'username' => '100500', 'description' => 'Provider B'],
]);
$result = $ambiguous->resolve(['line' => 'shared-peer', 'did' => '100500'], 'incoming');
trunkAssert('ambiguous', $result['status'], 'duplicate usernames are ambiguous');
trunkAssert('shared-peer', $result['name'], 'ambiguous result preserves technical value');
trunkAssert(2, count($result['candidates']), 'ambiguous candidates exposed');

echo "TrunkResolverTest: OK\n";
