<?php
namespace Tests\Feature; use Tests\TestCase;
class RuntimeHealthTest extends TestCase { public function test_runtime_health_endpoint_is_registered(): void { $this->assertTrue(collect(app('router')->getRoutes())->contains(fn($r)=>$r->getName()==='api.v3.chatbot.runtime.health')); } }
