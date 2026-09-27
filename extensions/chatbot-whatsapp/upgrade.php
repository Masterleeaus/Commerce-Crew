<?php
// Safe extension upgrade entry point for chatbot-whatsapp. The host installer remains authoritative.
return ['extension'=>'chatbot-whatsapp','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
