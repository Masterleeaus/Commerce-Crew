<?php
// Safe extension upgrade entry point for chatbot-booking. The host installer remains authoritative.
return ['extension'=>'chatbot-booking','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
