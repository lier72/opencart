<?php

if (extension_loaded('xmlrpc')) {
    echo "✅ XML-RPC extension is **loaded**.\n";
} else {
    echo "❌ XML-RPC extension is **NOT loaded**.\n";
}

// Optionally, check a core function
if (function_exists('xmlrpc_server_create')) {
    echo "✅ Core XML-RPC functions are available.\n";
} else {
    echo "❌ Core XML-RPC functions are NOT available.\n";
}

?>
