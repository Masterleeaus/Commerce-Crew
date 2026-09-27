<?php
// Safe extension upgrade entry point for chatbot-agent. The host installer remains authoritative.
return ['extension'=>'chatbot-agent','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
