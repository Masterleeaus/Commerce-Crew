<?php
// Safe extension upgrade entry point for chatbot. The host installer remains authoritative.
return ['extension'=>'chatbot','strategy'=>'additive-migrations','preserve_existing_data'=>true,'clear_cache'=>true];
