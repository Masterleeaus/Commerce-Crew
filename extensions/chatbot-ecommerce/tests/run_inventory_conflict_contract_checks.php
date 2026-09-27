<?php

declare(strict_types=1);

$checks=0;
$assert=static function(bool $condition,string $message) use (&$checks):void { $checks++; if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} };
$files=[
 'System/Support/InventoryChannelAllocator.php','System/Support/MarketplaceInventoryReconciliation.php','System/Support/InventoryConflictResolutionPolicy.php',
 'System/Models/MarketplaceInventoryPolicy.php','System/Models/MarketplaceInventoryMapping.php','System/Models/MarketplaceInventoryScanRun.php',
 'System/Models/MarketplaceInventoryConflict.php','System/Models/MarketplaceInventoryConflictEvent.php',
 'System/Services/MarketplaceInventoryReconciliationRuntime.php','System/Http/Controllers/Api/MarketplaceInventoryAdminApiController.php',
 'System/Http/Resources/Api/MarketplaceInventoryConflictResource.php','System/Http/Resources/Api/MarketplaceInventoryScanRunResource.php',
 'System/Jobs/ScanMarketplaceInventoryConflicts.php','System/Console/Commands/ScanMarketplaceInventoryConflictsCommand.php',
 'database/migrations/2026_08_03_060018_marketplace_inventory_conflict_resolution.php',
];
foreach($files as $file)$assert(is_file(__DIR__.'/../'.$file),"{$file} exists");
$runtime=file_get_contents(__DIR__.'/../System/Services/MarketplaceInventoryReconciliationRuntime.php');
foreach(['InventoryChannelAllocator::allocate','MarketplaceInventoryReconciliation::assess','InventoryConflictResolutionPolicy::evaluate','MarketplaceWriteRuntime','prepareCorrection','auto_map_by_sku','mapping_unverified','canonicalState','source_of_truth','update_inventory','approval_token'] as $needle)$assert(str_contains($runtime,$needle),"runtime contains {$needle}");
$provider=file_get_contents(__DIR__.'/../System/ChatbotEcommerceServiceProvider.php');
foreach(['MarketplaceInventoryAdminApiController','marketplace-inventory/policies','marketplace-inventory/mappings','inventory-scan','prepare-correction','ScanMarketplaceInventoryConflictsCommand'] as $needle)$assert(str_contains($provider,$needle),"provider contains {$needle}");
$tools=file_get_contents(__DIR__.'/../System/Services/MarketplaceToolRuntime.php');
foreach(['seller_marketplace_inventory_scan','seller_marketplace_inventory_conflicts','seller_marketplace_prepare_inventory_correction','seller_marketplace_acknowledge_inventory_conflict'] as $needle)$assert(str_contains($tools,$needle),"tool runtime contains {$needle}");
$config=file_get_contents(__DIR__.'/../config/platform-quality.php');
foreach(['inventory_conflict_resolution','inventory_reconciliation','maximum_sync_age_seconds','auto_prepare'] as $needle)$assert(str_contains($config,$needle),"config contains {$needle}");
$manifest=json_decode(file_get_contents(__DIR__.'/../extension.manifest.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['commerce.marketplace-inventory-reconciliation','commerce.inventory-conflict-resolution'] as $cap)$assert(in_array($cap,$manifest['capabilities']??[],true),"manifest declares {$cap}");
foreach(['ext_chatbot_marketplace_inventory_policies','ext_chatbot_marketplace_inventory_mappings','ext_chatbot_marketplace_inventory_scan_runs','ext_chatbot_marketplace_inventory_conflicts','ext_chatbot_marketplace_inventory_conflict_events'] as $table)$assert(in_array($table,$manifest['database']['owned_tables']??[],true),"manifest owns {$table}");
$openapi=file_get_contents(__DIR__.'/../openapi/conversational-commerce-v1.yaml');
foreach(['marketplace-inventory/conflicts','marketplace-inventory/mappings','inventory-scan'] as $needle)$assert(str_contains($openapi,$needle),"OpenAPI contains {$needle}");
fwrite(STDOUT,"Inventory conflict contract checks passed: {$checks}\n");
