<?php
// Safe extension upgrade entry point for chatbot-review. The host installer remains authoritative.
return ['extension'=>'chatbot-review','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
