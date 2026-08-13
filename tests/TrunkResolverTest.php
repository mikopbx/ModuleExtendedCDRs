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
    [
        'uniqid' => 'SIP-A',
        'username' => '+7 (495) 111-22-33',
        'description' => 'Provider A',
        'host' => ' Shared.Example.com ',
    ],
    [
        'uniqid' => 'SIP-B',
        'username' => '74952223344',
        'description' => 'Provider B',
        'host' => 'shared.example.com',
    ],
]);

$line = $resolver->resolve(['line' => 'SIP-A', 'did' => '74952223344'], 'incoming');
trunkAssert('Provider B', $line['name'], 'DID refines provider on shared host');
trunkAssert('SIP-B', $line['id'], 'refined provider id');
trunkAssert('did_username', $line['source'], 'shared host DID evidence source');

$missed = $resolver->resolve(['line' => 'SIP-A', 'did' => '7 495 222-33-44'], '3');
trunkAssert('Provider B', $missed['name'], 'missed call refines provider on shared host');

$outgoing = $resolver->resolve(['line' => 'SIP-A', 'did' => '74952223344'], 'outgoing');
trunkAssert('Provider A', $outgoing['name'], 'outgoing call retains line provider');
trunkAssert('line_id', $outgoing['source'], 'outgoing line evidence source');

$unknown = $resolver->resolve(['line' => 'unknown-peer', 'did' => '74952223344'], 'incoming');
trunkAssert('unresolved', $unknown['status'], 'unknown line does not trigger global DID lookup');

$differentHosts = new TrunkResolver([
    ['uniqid' => 'SIP-A', 'username' => '100', 'description' => 'Provider A', 'host' => 'a.example.com'],
    ['uniqid' => 'SIP-B', 'username' => '200', 'description' => 'Provider B', 'host' => 'b.example.com'],
]);
$differentHost = $differentHosts->resolve(['line' => 'SIP-A', 'did' => '200'], '2');
trunkAssert('Provider A', $differentHost['name'], 'DID on another host does not override line');

$emptyHosts = new TrunkResolver([
    ['uniqid' => 'SIP-A', 'username' => '100', 'description' => 'Provider A', 'host' => ''],
    ['uniqid' => 'SIP-B', 'username' => '200', 'description' => 'Provider B', 'host' => ''],
]);
$emptyHost = $emptyHosts->resolve(['line' => 'SIP-A', 'did' => '200'], '2');
trunkAssert('Provider A', $emptyHost['name'], 'empty hosts are not grouped');

$ambiguous = new TrunkResolver([
    ['uniqid' => 'SIP-A', 'username' => '100500', 'description' => 'Provider A', 'host' => 'shared.example.com'],
    ['uniqid' => 'SIP-B', 'username' => '100500', 'description' => 'Provider B', 'host' => 'shared.example.com'],
]);
$result = $ambiguous->resolve(['line' => 'SIP-A', 'did' => '100500'], 'incoming');
trunkAssert('Provider A', $result['name'], 'ambiguous shared-host DID retains line provider');
trunkAssert('line_id', $result['source'], 'ambiguous match falls back to line evidence');

$missingDid = $resolver->resolve(['line' => 'SIP-A', 'did' => ''], 'incoming');
trunkAssert('Provider A', $missingDid['name'], 'missing DID retains line provider');

echo "TrunkResolverTest: OK\n";
