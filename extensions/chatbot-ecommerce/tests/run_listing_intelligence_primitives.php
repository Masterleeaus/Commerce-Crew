<?php

declare(strict_types=1);

require_once __DIR__.'/../System/Support/BrandVoiceGuide.php';
require_once __DIR__.'/../System/Support/ClaimEvidenceValidator.php';
require_once __DIR__.'/../System/Support/ListingComplianceScanner.php';
require_once __DIR__.'/../System/Support/MarketplaceListingComposer.php';

use App\Extensions\ChatbotEcommerce\System\Support\BrandVoiceGuide;
use App\Extensions\ChatbotEcommerce\System\Support\ClaimEvidenceValidator;
use App\Extensions\ChatbotEcommerce\System\Support\ListingComplianceScanner;
use App\Extensions\ChatbotEcommerce\System\Support\MarketplaceListingComposer;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$guide = BrandVoiceGuide::fromExamples(['Friendly, practical advice.', 'Short sentences. No hype.'], ['warm', 'direct'], ['miracle', 'guaranteed']);
$assert($guide['tone'] === ['warm', 'direct'], 'brand voice preserves normalized tone');
$assert(in_array('miracle', $guide['forbidden_terms'], true), 'brand voice records forbidden terms');
$assert(strlen($guide['fingerprint']) === 64, 'brand voice has deterministic fingerprint');

$evidence = ClaimEvidenceValidator::validate([
    ['claim' => 'HEPA filtration', 'evidence_refs' => ['manual:p12']],
    ['claim' => 'Cures allergies', 'evidence_refs' => []],
]);
$assert($evidence['supported_count'] === 1, 'supported claims counted');
$assert($evidence['unsupported_count'] === 1, 'unsupported claims counted');
$assert($evidence['claims'][1]['status'] === 'unsupported', 'unsupported claim marked');

$scan = ListingComplianceScanner::scan(
    ['title' => 'Miracle Vacuum Guaranteed to Cure Allergies', 'description' => 'Best product ever.'],
    [
        ['code' => 'unsupported_health_claim', 'severity' => 'block', 'terms' => ['cure allergies']],
        ['code' => 'absolute_guarantee', 'severity' => 'warning', 'terms' => ['guaranteed']],
    ],
    $guide
);
$assert($scan['blocking_count'] === 1, 'blocking compliance finding counted');
$assert($scan['warning_count'] >= 1, 'warning finding counted');
$assert($scan['publishable'] === false, 'blocking finding prevents publication');

$amazon = MarketplaceListingComposer::compose('amazon', [
    'name' => 'Cordless Pet Vacuum',
    'brand' => 'Acme',
    'summary' => 'Lightweight cordless vacuum for pet hair and hard floors.',
    'features' => ['HEPA filter', '45 minute runtime', 'Wall mount'],
    'attributes' => ['colour' => 'Red', 'weight' => '2.5 kg'],
], $guide);
$assert(isset($amazon['title'], $amazon['bullet_points'], $amazon['description']), 'amazon composition includes required fields');
$assert(count($amazon['bullet_points']) <= 5, 'amazon composition limits bullet count');
$etsy = MarketplaceListingComposer::compose('etsy', ['name'=>'Handmade Soap','summary'=>'Small-batch soap','features'=>['Vegan','Lavender']], $guide);
$assert(isset($etsy['tags']) && count($etsy['tags']) <= 13, 'etsy composition produces bounded tags');
$ebay = MarketplaceListingComposer::compose('ebay', ['name'=>'Cordless Pet Vacuum','summary'=>'Clean floors quickly','attributes'=>['Brand'=>'Acme']], $guide);
$assert(isset($ebay['item_specifics']['Brand']), 'ebay composition preserves item specifics');

fwrite(STDOUT, "Listing intelligence primitive checks passed: {$checks}\n");
