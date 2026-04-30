<?php
/**
 * One-time script: Reset AI prompt to use the updated default template
 * Run once, then delete this file.
 */
require_once __DIR__ . '/wp-load.php';

// Delete stored prompt so the new default kicks in
delete_option('omnichannel_ai_assistant_prompt');
echo "AI prompt reset to default template. The new greeting/farewell behavior is now active.\n";
