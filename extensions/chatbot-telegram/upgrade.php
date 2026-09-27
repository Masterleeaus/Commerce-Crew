<?php
// Safe extension upgrade entry point for chatbot-telegram. The host installer remains authoritative.
return ['extension'=>'chatbot-telegram','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
