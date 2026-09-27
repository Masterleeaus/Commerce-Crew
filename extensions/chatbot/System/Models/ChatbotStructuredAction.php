<?php
namespace App\Extensions\Chatbot\System\Models;
use Illuminate\Database\Eloquent\Model;
class ChatbotStructuredAction extends Model {
 protected $table='ext_chatbot_structured_actions';
 protected $guarded=[];
 protected $casts=['payload'=>'array','result'=>'array','approved_at'=>'datetime','rejected_at'=>'datetime','executed_at'=>'datetime','cancelled_at'=>'datetime','expires_at'=>'datetime'];
 public function conversation(){ return $this->belongsTo(ChatbotConversation::class,'conversation_id'); }
 public function message(){ return $this->belongsTo(ChatbotHistory::class,'history_id'); }
}
