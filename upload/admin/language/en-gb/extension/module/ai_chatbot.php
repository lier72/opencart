<?php
// Heading
$_['heading_title']            = 'AI Chatbot';

// Text
$_['text_extension']           = 'Extensions';
$_['text_success']             = 'Success: You have modified the AI Chatbot module!';
$_['text_edit']                = 'Edit AI Chatbot Module';

// Entry
$_['entry_name']               = 'Module Name';
$_['entry_chat_service_url']   = 'Chat service URL';
$_['entry_shared_secret']      = 'Shared secret';
$_['entry_rate_limit']         = 'Rate limit, requests/minute';
$_['entry_welcome_message']    = 'Welcome message';
$_['entry_status']             = 'Status';
$_['entry_debug']              = 'Debug';

// Help
$_['help_chat_service_url']    = 'Base URL of the ai-chatbot Node service (services/ai-chatbot/), e.g. http://ai-chatbot:8787. The LLM API key lives only on that service — never here.';
$_['help_shared_secret']       = 'Must match the CHAT_SHARED_SECRET the Node service is configured with. Used to sign requests so the service can trust they came from this store — it is not an LLM key.';
$_['help_rate_limit']          = 'Per-visitor cap enforced here before any call reaches the chat service. Set to 0 to disable this layer (the service applies its own limit regardless).';
$_['help_welcome_message']     = 'Shown as the chatbot\'s first message. Leave blank to use the default.';
$_['help_debug']               = 'Writes chat proxy errors to system/storage/logs/ai_chatbot.log and returns them in the response instead of a generic error. Turn on only while diagnosing.';

// Error
$_['error_permission']         = 'Warning: You do not have permission to modify the AI Chatbot module!';
$_['error_name']               = 'Module Name must be between 3 and 64 characters!';
$_['error_chat_service_url']   = 'Chat service URL must be a valid URL!';
