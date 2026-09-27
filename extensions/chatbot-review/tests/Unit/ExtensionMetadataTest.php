<?php
namespace Tests\Unit;use PHPUnit\Framework\TestCase;
class ExtensionMetadataTest extends TestCase{public function test_extension_metadata_is_valid():void{$data=json_decode(file_get_contents(__DIR__.'/../../extension.json'),true,512,JSON_THROW_ON_ERROR);$this->assertSame('extension',$data['type']);$this->assertNotEmpty($data['name']);$this->assertNotEmpty($data['version']);}}
