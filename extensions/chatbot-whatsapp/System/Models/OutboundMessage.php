<?php
namespace App\Extensions\ChatbotWhatsapp\System\Models; use Illuminate\Database\Eloquent\Model;
class OutboundMessage extends Model { protected $table='ext_chatbot_messaging_outbound'; protected $guarded=[]; protected $casts=['payload'=>'array','provider_response'=>'array','scheduled_at'=>'datetime','sent_at'=>'datetime','delivered_at'=>'datetime','read_at'=>'datetime']; }
