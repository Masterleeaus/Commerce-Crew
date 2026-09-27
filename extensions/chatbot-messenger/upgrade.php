<?php
// Safe extension upgrade entry point for chatbot-messenger. The host installer remains authoritative.
return ['extension'=>'chatbot-messenger','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
