<?php
namespace App\Extensions\ChatbotAgent\System\Policies;
class ExtensionAccessPolicy{public function use(mixed$user):bool{return $user!==null;}public function administer(mixed$user):bool{return (bool)($user->isAdmin()??false);}}
