<?php
namespace App\Extensions\ChatbotReview\System\Models;use Illuminate\Database\Eloquent\Model;
class Feedback extends Model{protected $table='ext_chatbot_feedback';protected $guarded=[];protected $casts=['answers'=>'array','metadata'=>'array','follow_up_at'=>'datetime'];}
