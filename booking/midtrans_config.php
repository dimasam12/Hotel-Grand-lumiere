<?php
define('MIDTRANS_SERVER_KEY', getenv('MIDTRANS_SERVER_KEY') ?: '');
define('MIDTRANS_CLIENT_KEY', getenv('MIDTRANS_CLIENT_KEY') ?: '');
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_API_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');