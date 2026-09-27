<?php
namespace App\Extensions\ChatbotBooking\System\Models;use Illuminate\Database\Eloquent\Model;
class Appointment extends Model{protected $table='ext_chatbot_appointments';protected $guarded=[];protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','metadata'=>'array'];}
