<?php
// Safe extension upgrade entry point for chatbot-voice. The host installer remains authoritative.
return ['extension'=>'chatbot-voice','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
