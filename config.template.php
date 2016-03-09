<?php

// The default timezone for you and your team
define('DEFAULT_TIMEZONE', 'Europe/Berlin');

// Your database connection settings
define('DB_NAME'    , 'sabredav');
define('DB_HOST'    , '127.0.0.1');
define('DB_USER'    , 'sabredav');
define('DB_PASSWORD', 'MySecretPassword');

// Your ZeyOS instance ID, e.g. https://cloud.zeyos.com/myinstance/
define('ZEYOS_ID'   , 'myinstance');

// If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
// You can override the baseUri here.
define('BASE_URI'   , '');

// Trusted Hosts may use the "Truested Password" feature, where the password
// is a MD5 hash consisting of md5(TRUSTED_SALT.$username)
define('TRUSTED_HOST' , '');
define('TRUSTED_SALT' , '');

// Token to secure the provisioning service
define('AUTH_TOKEN' , 'MyRandomProvisioningToken');