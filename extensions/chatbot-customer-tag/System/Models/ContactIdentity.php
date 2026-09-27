<?php
namespace App\Extensions\ChatbotCustomerTag\System\Models; use Illuminate\Database\Eloquent\Model;
class ContactIdentity extends Model{protected $table='ext_chatbot_contact_identities';protected $guarded=[];protected $casts=['verified_at'=>'datetime','metadata'=>'array'];}
