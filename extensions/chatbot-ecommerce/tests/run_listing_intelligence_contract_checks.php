<?php

declare(strict_types=1);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};
$files = [
    'System/Models/BrandVoiceProfile.php',
    'System/Models/ProductContentProfile.php',
    'System/Models/ListingComplianceRule.php',
    'System/Models/ListingIntelligenceRun.php',
    'System/Models/ListingIntelligenceFinding.php',
    'System/Models/ListingRewriteProposal.php',
    'System/Services/ListingIntelligenceRuntime.php',
    'System/Http/Controllers/Api/ListingIntelligenceAdminApiController.php',
    'System/Http/Resources/Api/ListingIntelligenceRunResource.php',
    'System/Support/BrandVoiceGuide.php',
    'System/Support/ClaimEvidenceValidator.php',
    'System/Support/ListingComplianceScanner.php',
    'System/Support/MarketplaceListingComposer.php',
    'database/migrations/2026_08_03_060017_listing_intelligence_compliance.php',
];
foreach ($files as $file) $assert(is_file(__DIR__.'/../'.$file), "{$file} exists");
$provider = file_get_contents(__DIR__.'/../System/ChatbotEcommerceServiceProvider.php');
foreach (['ListingIntelligenceAdminApiController', 'brand-voice-profiles', 'listing-intelligence-runs', 'rewrite-proposals', 'prepare-write'] as $needle) $assert(str_contains($provider, $needle), "provider contains {$needle}");
$runtime = file_get_contents(__DIR__.'/../System/Services/ListingIntelligenceRuntime.php');
foreach (['MarketplaceListingComposer::compose', 'ListingComplianceScanner::scan', 'ClaimEvidenceValidator::validate', 'MarketplaceWriteRuntime', 'update_listing', 'source_hash', 'belongs to another marketplace connection', 'owner_user_id'] as $needle) $assert(str_contains($runtime, $needle), "runtime contains {$needle}");
$tools = file_get_contents(__DIR__.'/../System/Services/MarketplaceToolRuntime.php');
foreach (['seller_marketplace_analyse_listing', 'seller_marketplace_generate_rewrite', 'seller_marketplace_prepare_rewrite_write'] as $needle) $assert(str_contains($tools, $needle), "tool runtime contains {$needle}");
$manifest = json_decode(file_get_contents(__DIR__.'/../extension.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(in_array('commerce.listing-intelligence', $manifest['capabilities'] ?? [], true), 'manifest declares listing intelligence');
$assert(in_array('commerce.marketplace-compliance', $manifest['capabilities'] ?? [], true), 'manifest declares marketplace compliance');
foreach (['ext_chatbot_brand_voice_profiles','ext_chatbot_product_content_profiles','ext_chatbot_listing_compliance_rules','ext_chatbot_listing_intelligence_runs','ext_chatbot_listing_intelligence_findings','ext_chatbot_listing_rewrite_proposals'] as $table) {
    $assert(in_array($table, $manifest['database']['owned_tables'] ?? [], true), "manifest owns {$table}");
}
fwrite(STDOUT, "Listing intelligence contract checks passed: {$checks}\n");
